<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoringMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        // Trace ID لكل طلب
        $traceId = uniqid('trace_', true);

        // نضيف الـ Trace للـ Context تبع الـ Logs
        Log::withContext([
            'trace_id' => $traceId,
            'path' => $request->path(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
        ]);

        $response = $next($request);

        $executionTime = round((microtime(true) - $start) * 1000, 2);

        Log::channel('monitoring')->info('HTTP Performance', [
            'response_time_ms' => $executionTime,
            'status_code' => $response->getStatusCode(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // نرجع الـ Trace
        $response->headers->set('X-Trace-Id', $traceId);
        $response->headers->set('X-Execution-Time', $executionTime.'ms');

        return $response;
    }
}
