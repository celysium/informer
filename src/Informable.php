<?php

namespace Celysium\Informer;

use Celysium\Request\Exceptions\BadRequestHttpException;
use Celysium\Request\Facades\RequestBuilder;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @uses Model
 */
trait Informable
{
    /**
     * @param Model $model
     * @param string|null $identity
     * @param $action
     * @return array
     * @throws Exception
     */
    public function getEntityModel(Model $model, string $identity = null, $action = null): array
    {
        return [
            "entity" => $model->entity ?? $model->getTable(),
            "identity" => $model->getAttribute($identity ?? $model->getKeyName()) ?? throw new Exception("can not retrieve identity key."),
            "action" => $action ?? ($this->isSoftDeletable($model) && $model->getAttribute('deleted_at') ? "deleted" : "retrieved")
        ];
    }


    /**
     * @param $identity
     * @param $action
     * @return void
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function sync($identity = null, $action = null): void
    {
        /** @var Model $this */
        $this->send([$this->getEntityModel($this, $identity, $action)]);
    }

    /**
     * @param Collection $collections
     * @param string|null $identity
     * @param string|null $action
     * @param int $chunkSize
     * @return void
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function batch(Collection $collections, string $identity = null, string $action = null, int $chunkSize = 100): void
    {
        /** @var Model $this */
        foreach ($collections->chunk($chunkSize) as $chunked) {
            $data = [];

            /** @var self $model */
            foreach ($chunked as $model) {
                $data = array_merge($data, [$model->getEntityModel($this, $identity, $action)]);
            }
            $this->send($data);
        }
    }

    /**
     * @param array $data
     * @return void
     * @throws BadRequestHttpException
     */
    public function send(array $data): void
    {
        RequestBuilder::request()->post('/validator', $data)->onError(fn($response) => throw new BadRequestHttpException($response));
    }


    /**
     * @param $identity
     * @return void
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function syncRestored($identity = null): void
    {
        /** @var Model $this */
        $this->send([$this->getEntityModel($this, $identity, 'restored')]);
    }


    /**
     * @param Collection $collection
     * @param $identity
     * @param int $chunkSize
     * @return void
     * @throws BadRequestHttpException
     */
    public function batchRestored(Collection $collection, $identity = null, int $chunkSize = 100): void
    {
        $this->batch($collection, $identity, 'restored', $chunkSize);
    }


    /**
     * @param string|null $identity
     * @return void
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function syncCreated(string $identity = null): void
    {
        /** @var Model $this */
        $this->send([$this->getEntityModel($this, $identity, 'created')]);
    }


    /**
     * @param Collection $collection
     * @param string|null $identity
     * @param int $chunkSize
     * @return void
     * @throws BadRequestHttpException
     */
    public function syncBatchCreated(Collection $collection, string $identity = null, int $chunkSize = 100): void
    {
        $this->batch($collection, $identity, 'created', $chunkSize);
    }


    /**
     * @param string|null $identity
     * @return void
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function syncDeleted(string $identity = null): void
    {
        $action = $this->isSoftDeletable($this) ? 'deleted' : 'forceDeleted';

        /** @var Model $this */
        $this->send([$this->getEntityModel($this, $identity, $action)]);

    }


    /**
     * @param Collection $collection
     * @param string|null $identity
     * @param int $chunkSize
     * @return void
     * @throws BadRequestHttpException
     */
    public function syncBatchDeleted(Collection $collection, string $identity = null, int $chunkSize = 100): void
    {
        $action = $this->isSoftDeletable($this) ? 'deleted' : 'forceDeleted';
        $this->batch($collection, $identity, $action, $chunkSize);
    }


    /**
     * @param string|null $identity
     * @return void
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function syncForceDeleted(string $identity = null): void
    {
        /** @var Model $this */
        $this->send([$this->getEntityModel($this, $identity, 'forceDeleted')]);
    }


    /**
     * @param Collection $collection
     * @param string|null $identity
     * @param int $chunkSize
     * @return void
     * @throws BadRequestHttpException
     */
    public function syncBatchForceDeleted(Collection $collection, string $identity = null, int $chunkSize = 100): void
    {
        $this->batch($collection, $identity, 'forceDeleted', $chunkSize);
    }

    /**
     * Determines whether model has used softDelete or not
     *
     * @param $model
     * @return bool
     */
    public function isSoftDeletable($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }


}