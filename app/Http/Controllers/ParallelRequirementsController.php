<?php

namespace App\Http\Controllers;

use App\Services\BenchmarkService;
use App\Services\InventoryLockService;
use App\Services\LoadBalancerService;
use App\Services\ProductCacheService;
use App\Services\StressTestService;
use Illuminate\Http\Request;

class ParallelRequirementsController extends Controller
{
    public function processNode(Request $request)
    {
        $port = $request->getPort();
        $task = (int) $request->query('task', 0);

        return response()->json([
            'message' => 'Task handled by independent Laravel node.',
            'task' => $task,
            'handled_by' => 'node-' . $port,
            'port' => $port,
            'process_id' => getmypid(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function nextServer(LoadBalancerService $loadBalancer)
    {
        return response()->json([
            'requirement' => '5 - Load Distribution',
            'result' => $loadBalancer->nextServerRoundRobin(),
        ]);
    }

    public function simulateLoadDistribution(Request $request, LoadBalancerService $loadBalancer)
    {
        $requests = (int) $request->query('requests', 12);

        return response()->json([
            'requirement' => '5 - Load Distribution',
            'result' => $loadBalancer->simulate($requests),
        ]);
    }

    public function popularProducts(Request $request, ProductCacheService $productCache)
    {
        $limit = (int) $request->query('limit', 10);

        return response()->json([
            'requirement' => '6 - Distributed Caching / Cache Strategy',
            'result' => $productCache->getPopularProducts($limit),
        ]);
    }

    public function clearPopularProductsCache(ProductCacheService $productCache)
    {
        $productCache->forgetPopularProducts();

        return response()->json([
            'message' => 'Popular products cache cleared.',
        ]);
    }

    public function inventoryState(InventoryLockService $inventory)
    {
        return response()->json([
            'requirement' => '7 & 8 - Shared demo inventory state',
            'state' => $inventory->state(),
        ]);
    }

    public function resetInventory(Request $request, InventoryLockService $inventory)
    {
        return response()->json([
            'requirement' => '7 & 8 - Reset shared demo inventory state',
            'result' => $inventory->reset(
                (int) $request->query('stock', 10),
                (int) $request->query('balance', 1000),
            ),
        ]);
    }

    public function unsafePurchase(Request $request, InventoryLockService $inventory)
    {
        return response()->json([
            'requirement' => '7 - Race condition demo without lock',
            'result' => $inventory->unsafePurchase((int) $request->query('quantity', 1)),
        ]);
    }

    public function lockedPurchase(Request $request, InventoryLockService $inventory)
    {
        return response()->json([
            'requirement' => '7 - Concurrency Control with shared lock',
            'result' => $inventory->lockedPurchase((int) $request->query('quantity', 1)),
        ]);
    }

    public function transactionPurchase(Request $request, InventoryLockService $inventory)
    {
        return response()->json([
            'requirement' => '8 - ACID / Transaction Integrity',
            'result' => $inventory->transactionPurchase(
                (int) $request->query('quantity', 1),
                filter_var($request->query('fail_after_stock', false), FILTER_VALIDATE_BOOLEAN),
            ),
        ]);
    }

    public function runStressTest(Request $request, StressTestService $stressTest)
    {
        return response()->json([
            'requirement' => '9 - Concurrent Stress Testing',
            'result' => $stressTest->run((int) $request->query('requests', 100)),
        ]);
    }

    public function runBenchmark(Request $request, BenchmarkService $benchmark)
    {
        return response()->json([
            'requirement' => '10 - Bottleneck Analysis & Benchmarking',
            'result' => $benchmark->run((int) $request->query('items', 5000)),
        ]);
    }
    public function benchmark()
{
    return response()->json([
        'requirement' =>
            '10 - Benchmarking & Bottleneck Analysis',

        'result' =>
            app(\App\Services\BenchmarkService::class)
                ->analyze()
    ]);
}
}
