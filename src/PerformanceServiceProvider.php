<?php

namespace Timekit\Performance;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class PerformanceServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $this->app->singleton(QueryContainer::class, function ($app) {
            return new QueryContainer($app->make(Request::class), $app->make(Filesystem::class), storage_path('performance/'));
        });

        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $event) {
            $container = $this->app->make(QueryContainer::class);
            $container->addByEvent($event);
        });
    }
}
