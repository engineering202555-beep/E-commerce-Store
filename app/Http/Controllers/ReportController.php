<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function before()
    {
        $start = microtime(true);

        $orders = DeliveryOrder::with('orderItems')
            ->whereDate('created_at', today())
            ->where('status', 'done')
            ->get();

        $totalRevenue = 0;
        $totalMeals = 0;

        foreach ($orders as $order) {

            $totalRevenue += $order->total_price;

            foreach ($order->orderItems as $item) {
                $totalMeals += $item->quantity;
            }
        }

        $time = round((microtime(true) - $start) * 1000, 2);

        return response()->json([
            'orders_count' => $orders->count(),
            'execution_time_ms' => $time,
            'memory_mb' => round(memory_get_peak_usage(true)/1024/1024, 2),
            'revenue' => $totalRevenue,
            'meals' => $totalMeals
        ]);
    }

    public function after()
    {
        $start = microtime(true);

        $totalRevenue = 0;
        $totalMeals = 0;

        DeliveryOrder::with('orderItems')
            ->whereDate('created_at', today())
            ->where('status', 'done')
            ->chunkById(500, function ($orders)
            use (&$totalRevenue, &$totalMeals) {

                foreach ($orders as $order) {

                    $totalRevenue += $order->total_price;

                    foreach ($order->orderItems as $item) {
                        $totalMeals += $item->quantity;
                    }
                }
            });

        $time = round((microtime(true) - $start) * 1000, 2);

        return response()->json([
            'execution_time_ms' => $time,
            'memory_mb' => round(memory_get_peak_usage(true)/1024/1024, 2),
            'revenue' => $totalRevenue,
            'meals' => $totalMeals
        ]);
    }
}
