<?php

namespace Timekit\Performance;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;

class PerformanceServiceProvider extends ServiceProvider
{
    public function register(Dispatcher $dispatcher)
    {
        $this->app->singleton(QueryContainer::class, $this->app->make(QueryContainer::class));

        $dispatcher->listen(QueryExecuted::class, function(QueryExecuted $event) {
            $container = $this->app->make(QueryContainer::class);
            $container->addByEvent($event);
        });
    }
}
