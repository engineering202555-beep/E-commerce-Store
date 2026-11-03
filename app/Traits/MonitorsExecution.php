<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Throwable;

trait MonitorsExecution
{
    protected function monitorExecution(string $name, callable $callback, array $context = [])
    {
        $start   = microtime(true);
        $failed  = false;

        try {
            $result = $callback();

            $duration = round((microtime(true) - $start) * 1000, 2);

            Log::channel('monitoring')->info($name.' success', array_merge($context, [
                'failed' => false,
                'response_time_ms' => $duration,
            ]));

            return $result;

        } catch (Throwable $e) {

            $duration = round((microtime(true) - $start) * 1000, 2);

            Log::channel('monitoring')->error($name.' failed', array_merge($context, [
                'failed' => true,
                'response_time_ms' => $duration,
                'error' => $e->getMessage(),
            ]));

            throw $e;
        }
    }
}
