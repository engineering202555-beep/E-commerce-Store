<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoadBalancerService
{
    /**
     * Port 8000 is only the Load Balancer.
     * Real worker nodes are independent Laravel instances on 8001, 8002, 8003.
     */
    private array $servers = [
        [
            'id' => 'node-8001',
            'name' => 'Backend Node on port 8001',
            'port' => 8001,
            'url' => 'http://127.0.0.1:8001/api/parallel/node/process',
        ],
        [
            'id' => 'node-8002',
            'name' => 'Backend Node on port 8002',
            'port' => 8002,
            'url' => 'http://127.0.0.1:8002/api/parallel/node/process',
        ],
        [
            'id' => 'node-8003',
            'name' => 'Backend Node on port 8003',
            'port' => 8003,
            'url' => 'http://127.0.0.1:8003/api/parallel/node/process',
        ],
    ];

    public function servers(): array
    {
        return $this->servers;
    }

    public function nextServerRoundRobin(): array
    {
        $key = 'parallel_project:load_balancer:round_robin_counter';

        $counter = cache()->increment($key);

        if ($counter === false || $counter === null) {
            cache()->put($key, 1, now()->addDay());
            $counter = 1;
        }

        $index = ((int) $counter - 1) % count($this->servers);
        $server = $this->servers[$index];

        Log::channel('monitoring')->info('LOAD_BALANCER_DECISION', [
            'strategy' => 'Round Robin over Multiple Laravel Instances',
            'counter' => $counter,
            'selected_node' => $server['id'],
            'selected_url' => $server['url'],
        ]);

        return [
            'strategy' => 'Round Robin over Multiple Laravel Instances',
            'counter' => $counter,
            'selected_server' => $server,
        ];
    }

    public function simulate(int $requests): array
    {
        $requests = max(1, min($requests, 1000));

        $distribution = collect($this->servers)->mapWithKeys(fn ($server) => [
            $server['id'] => 0,
        ])->toArray();

        $timeline = [];
        $success = 0;
        $failed = 0;
        $startedAt = microtime(true);

        for ($i = 1; $i <= $requests; $i++) {
            // Deterministic Round Robin for clean testing:
            // 1 -> 8001, 2 -> 8002, 3 -> 8003, 4 -> 8001 ...
            $server = $this->servers[($i - 1) % count($this->servers)];
            $taskStartedAt = microtime(true);

            try {
                $response = Http::timeout(5)->get($server['url'], [
                    'task' => $i,
                ]);

                $durationMs = round((microtime(true) - $taskStartedAt) * 1000, 2);

                if ($response->successful()) {
                    $success++;
                    $distribution[$server['id']]++;
                    $nodePayload = $response->json();

                    $timeline[] = [
                        'task' => $i,
                        'selected_node' => $server['id'],
                        'selected_port' => $server['port'],
                        'handled_by' => $nodePayload['handled_by'] ?? $server['id'],
                        'process_id' => $nodePayload['process_id'] ?? null,
                        'status' => 'success',
                        'response_time_ms' => $durationMs,
                    ];
                } else {
                    $failed++;

                    $timeline[] = [
                        'task' => $i,
                        'selected_node' => $server['id'],
                        'selected_port' => $server['port'],
                        'status' => 'failed',
                        'http_status' => $response->status(),
                        'response_time_ms' => $durationMs,
                    ];
                }
            } catch (\Throwable $e) {
                $failed++;

                $timeline[] = [
                    'task' => $i,
                    'selected_node' => $server['id'],
                    'selected_port' => $server['port'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalDurationMs = round((microtime(true) - $startedAt) * 1000, 2);

        Log::channel('monitoring')->info('LOAD_BALANCER_SIMULATION_RESULT', [
            'total_requests' => $requests,
            'success_requests' => $success,
            'failed_requests' => $failed,
            'distribution' => $distribution,
            'total_response_time_ms' => $totalDurationMs,
        ]);

        return [
            'total_requests' => $requests,
            'success_requests' => $success,
            'failed_requests' => $failed,
            'strategy' => 'Round Robin',
            'simulation_type' => 'Multiple independent Laravel instances on different ports',
            'reason' => 'Round Robin مناسب عندما تكون الخوادم متقاربة بالقوة لأنه يوزع الطلبات بالتساوي ويمنع تركيز الضغط على Instance واحدة.',
            'load_balancer_port' => 8000,
            'required_running_nodes' => [8001, 8002, 8003],
           /* 'expected_distribution_for_12_requests' => [
                'node-8001' => 4,
                'node-8002' => 4,
                'node-8003' => 4,
            ],*/
            'distribution' => $distribution,
            'total_response_time_ms' => $totalDurationMs,
            'timeline' => $timeline,
        ];
    }
}