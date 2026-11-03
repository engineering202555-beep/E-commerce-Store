<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductCacheService
{
    private string $cacheKey = 'parallel_project:popular_products:v1';
    private int $ttlSeconds = 300;

    public function getPopularProducts(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $key = $this->cacheKey . ':limit_' . $limit;
        $startedAt = microtime(true);

        $products = [];
        $source = 'unknown';

        if (Cache::has($key)) {
            $products = Cache::get($key);
            $source = 'file_cache_hit';
        } else {
            try {
                $store = Cache::store('redis');
                $isRedisHit = $store->has($key);

                $products = $store->remember($key, $this->ttlSeconds, function () use ($limit) {
                    Log::channel('monitoring')->info('POPULAR_PRODUCTS_CACHE_MISS_DATABASE_QUERY');

                    return MenuItem::query()
                        ->with('menuCategory:id,name')
                        ->withCount('orderItems')
                        ->orderByDesc('order_items_count')
                        ->orderByDesc('id')
                        ->limit($limit)
                        ->get()
                        ->map(fn ($item) => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'category' => $item->menuCategory?->name,
                            'price' => $item->price,
                            'is_available' => (bool) $item->is_available,
                            'orders_count' => $item->order_items_count,
                        ])
                        ->toArray();
                });

                $source = $isRedisHit ? 'redis_cache_hit' : 'database_then_saved_to_redis';
                Cache::put($key, $products, $this->ttlSeconds);
            } catch (\Throwable $e) {
                Log::channel('monitoring')->error('POPULAR_PRODUCTS_REDIS_OR_DATABASE_FAILED_USING_DEMO_DATA', [
                    'error' => $e->getMessage(),
                ]);

                $products = collect(range(1, $limit))->map(fn ($i) => [
                    'id' => $i,
                    'name' => 'Demo Product ' . $i,
                    'category' => 'Demo Category',
                    'price' => 1000 * $i,
                    'is_available' => true,
                    'orders_count' => 50 - $i,
                ])->toArray();

                Cache::put($key, $products, $this->ttlSeconds);
                $source = 'demo_data_saved_to_file_cache';
            }
        }

        $duration = round((microtime(true) - $startedAt) * 1000, 2);

        Log::channel('monitoring')->info('POPULAR_PRODUCTS_CACHE_RESULT', [
            'source' => $source,
            'cache_store' => config('cache.default'),
            'limit' => $limit,
            'response_time_ms' => $duration,
        ]);

        return [
            'requirement' => '6 - Distributed Caching / Cache Strategy',
            'cache_store' => config('cache.default'),
            'cache_key' => $key,
            'ttl_seconds' => $this->ttlSeconds,
            'source' => $source,
            'response_time_ms' => $duration,
            'explanation' => $source === 'file_cache_hit'
                ? 'The data was served from cache without querying the database.'
                : 'The data was generated/read once and then stored in cache for the next requests.',
            'products' => $products,
        ];
    }

    public function forgetPopularProducts(): void
    {
        for ($limit = 1; $limit <= 50; $limit++) {
            $key = $this->cacheKey . ':limit_' . $limit;
            Cache::forget($key);

            try {
                Cache::store('redis')->forget($key);
            } catch (\Throwable $e) {
                // Redis is optional for the local demo.
            }
        }

        Log::channel('monitoring')->info('POPULAR_PRODUCTS_CACHE_CLEARED', [
            'cache_store' => config('cache.default'),
        ]);
    }
}
