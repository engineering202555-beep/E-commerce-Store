<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ParallelRequirementAspectMiddleware
{
    /**
     * AOP-style cross-cutting concern:
     * This middleware monitors performance for requirement demos without mixing
     * monitoring code inside the business logic of load balancing or caching.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $memoryStart = memory_get_usage(true);
        $aspectName = $request->route()?->getName() ?? $request->path();

        Log::channel('monitoring')->info('AOP_BEFORE_REQUIREMENT_DEMO', [
            'aspect' => $aspectName,
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);
        $memoryDiff = round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2);

        Log::channel('monitoring')->info('AOP_AFTER_REQUIREMENT_DEMO', [
            'aspect' => $aspectName,
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => $duration,
            'memory_diff_mb' => $memoryDiff,
        ]);

        $response->headers->set('X-AOP-Aspect', 'ParallelRequirementAspectMiddleware');
        $response->headers->set('X-AOP-Execution-Time', $duration . 'ms');

        return $response;
    }
}
