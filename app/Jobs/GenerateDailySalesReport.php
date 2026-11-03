<?php

namespace App\Jobs;

use App\Models\DeliveryOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class GenerateDailySalesReport implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $jobs = [];

        DeliveryOrder::whereDate('created_at', today())
            ->where('status', 'done')
            ->chunkById(500, function ($orders) use (&$jobs) {

                $jobs[] = new ProcessSalesChunk(
                    $orders->pluck('id')->toArray()
                );

            });

        Bus::batch($jobs)
            ->name('daily-sales-report')
            ->dispatch();

        Log::info('Daily sales batch dispatched', [
            'chunks' => count($jobs)
        ]);
    }
}
