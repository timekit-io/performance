<?php

namespace Timekit\Performance\Http\Middleware;

use Closure;
use Timekit\Performance\QueryContainer;

class PerformanceTracking
{
    /**
     * @var QueryContainer
     */
    private $container;

    /**
     * PerformanceTracking constructor.
     * @param QueryContainer $container
     */
    public function __construct(QueryContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        return $response;
    }

    public function terminate($request, $response)
    {
        $this->container->save();
    }
}