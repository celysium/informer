<?php

namespace Celysium\Informer;

use Illuminate\Support\ServiceProvider;
use Celysium\Informer\Commands\InformerSync;

class InformerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            InformerSync::class
        ]);
    }

    public function register()
    {
        //
    }
}
