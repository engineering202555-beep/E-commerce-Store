<?php

namespace App\Jobs;

use App\Models\DeliveryOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessSalesChunk implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $orderIds)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orders = DeliveryOrder::with('orderItems.menuItem')
            ->whereIn('id', $this->orderIds)
            ->get();

        $totalRevenue = 0;
        $totalMeals = 0;

        foreach ($orders as $order) {

            $totalRevenue += $order->total_price;

            foreach ($order->orderItems as $item) {
                $totalMeals += $item->quantity;
            }
        }

        Log::info('Chunk processed', [
            'orders' => count($this->orderIds),
            'revenue' => $totalRevenue,
            'meals' => $totalMeals,
        ]);
    }
}
