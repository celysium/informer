<?php

namespace Celysium\Informer\Commands;

use Celysium\Informer\Informable;
use Celysium\Request\Exceptions\BadRequestHttpException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;

class InformerSync extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'informer:sync
                                {--model=* : Class names of the models to be synced}
                                {--except=* : Class names of the models to be excluded from synced}
                                {--chunk=100 : The number of models to retrieve per chunk of models to be synced}
                                {--pretend : Display the number of synced records found instead of syncing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync models with validation service';


    /**
     * @return void
     * @throws BadRequestHttpException
     */
    public function handle(): void
    {
        $models = $this->models();

        if ($models->isEmpty()) {

            $this->info('No informable models found.');
            return;
        }

        if ($this->option('pretend')) {

            $models->each(function ($model) {
                $this->pretendToSync($model);
            });
            return;
        }

        $chunkSize = $this->option('chunk');
        $models->each(function ($model) use ($chunkSize) {
            $this->info("Start sync [$model] ...");
            $instance = new $model;

            $total = 0;
            if ($this->isValidatable($model)) {
                $total = $this->sync($instance, $chunkSize);
            }

            if ($total == 0) {
                $this->info("No sync [$model] records found.");
            }
        });
    }

    /**
     * Determine the models that should be synced.
     *
     * @return Collection
     */
    protected function models(): Collection
    {
        if (!empty($models = $this->option('model'))) {
            return collect($models)->filter(function ($model) {
                return class_exists($model);
            })->values();
        }

        $except = $this->option('except');

        if (!empty($models) && !empty($except)) {
            throw new InvalidArgumentException('The --models and --except options cannot be combined.');
        }
        $app_path = app_path();
        $real_path = realpath($app_path) . DIRECTORY_SEPARATOR;
        $namespace = app()->getNamespace();
        return collect((new Finder)->in($app_path)->files()->name('*.php'))
            ->map(function ($model) use ($real_path, $namespace) {

                return $namespace . str_replace(['/', '.php'], ['\\', ''], ltrim($model->getRealPath(), $real_path));
            })->filter(function ($model) use ($except) {
                return class_exists($model) &&
                    (!empty($except) && !in_array($model, $except)) &&
                    $this->isModel($model) &&
                    $this->isValidatable($model);
            })->values();
    }

    /**
     * Determine if the given model class is syncable.
     *
     * @param string $model
     * @return bool
     */
    protected function isModel(string $model): bool
    {
        $uses = class_parents($model);
        return in_array(Model::class, $uses) && !in_array(Pivot::class, $uses);
    }

    /**
     * Determine if the given model class is informable.
     *
     * @param string $model
     * @return bool
     */
    protected function isValidatable(string $model): bool
    {
        return in_array(Informable::class, class_uses_recursive($model));
    }

    /**
     * Determine is a model used SoftDelete trait
     *
     * @param string $model
     * @return bool
     */
    protected function isSoftDeletable(string $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * Display how many models will be Informable.
     *
     * @param string $model
     * @return void
     */
    protected function pretendToSync(string $model): void
    {
        $instance = new $model;

        $count = $instance
            ->when($this->isSoftDeletable(get_class($instance)), function ($query) {
                $query->withTrashed();
            })->count();

        if ($count) {
            $this->info("$count [$model] records will be sync.");
            return;
        }

        $this->info("No Informable [$model] records found.");

    }


    /**
     * batch collection of a given model
     *
     * @param Model $instance
     * @param int $chunkSize
     * @return int
     * @throws BadRequestHttpException
     */
    public function sync(Model $instance, int $chunkSize = 100): int
    {
        $isSoftDeletable = $this->isSoftDeletable(get_class($instance));

        $query = $instance->query()->when($isSoftDeletable, fn(Builder $query) => $query->withTrashed());

        $total = 0;
        $query->chunk($chunkSize, function (Collection $items) use ($instance, $chunkSize, &$total) {

            $total += $count = $items->count();
            if ($count) {
                /** @var Informable $instance */
                $instance->batch($items, chunkSize: $chunkSize);
            }
        });

        return $total;
    }
}
