<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\InOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainInfoController extends Controller
{

    public function getDailyCompletedBillInCount()
    {
        $today = now()->toDateString(); // YYYY-MM-DD

        $count = InOrder::whereDate('created_at', $today)
            ->where('status', 'done')
            ->count();

        return response()->json([
            'message' => 'Daily completed delivery orders count',
            'date' => $today,
            'completed_in_orders' => $count
        ]);
    }
    public function getDailyCompletedDeliveryCount()
    {
        $today = now()->toDateString(); // YYYY-MM-DD

        $count = DeliveryOrder::whereDate('created_at', $today)
            ->where('status', 'done')
            ->count();

        return response()->json([
            'message' => 'Daily completed delivery orders count',
            'date' => $today,
            'completed_delivery_orders' => $count
        ]);
    }

    public function getDailySales()
    {
        $today = now()->toDateString();

        $inOrdersTotal = InOrder::whereDate('created_at', $today)
            ->where('status', 'done')
            ->sum('total_price');

        $deliveryOrdersTotal = DeliveryOrder::whereDate('created_at', $today)
            ->where('status', 'done')
            ->sum('total_price');

        $totalSales = $inOrdersTotal + $deliveryOrdersTotal;

        return response()->json([
            'message' => 'Daily sales summary',
            'date' => $today,
            'total_sales' => $totalSales
        ]);
    }

    public function getTopMeals()
    {
        $topMeals = DB::table('order_items')
            ->select('menu_item_id', DB::raw('SUM(quantity) as total_quantity'))
            ->where('status', 'done')
            ->groupBy('menu_item_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        $topMeals = $topMeals->map(function ($meal) {
            $menuItem = DB::table('menu_items')->where('id', $meal->menu_item_id)->first();
            return [
                'menu_item_id' => $meal->menu_item_id,
                'name' => $menuItem->name,
                'price'=> $menuItem->price,
                'total_orders' => $meal->total_quantity
            ];
        });

        return response()->json([
            'message' => 'Top 5 ordered meals',
            'data' => $topMeals
        ]);
    }

    public function getLastOrders()
    {
        $orders = DB::table('order_items')
            ->where('status', 'done')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $orders = $orders->map(function ($order) {
            $menuItem = DB::table('menu_items')->where('id', $order->menu_item_id)->first();
            return [
                'order_id' => $order->id,
                'menu_item_id' => $order->menu_item_id,
                'name' => $menuItem->name,
                'quantity' => $order->quantity,
                'order_type' => $order->order_type,
                'created_at' => $order->created_at
            ];
        });

        return response()->json([
            'message' => 'Last 5 completed orders',
            'data' => $orders
        ]);
    }




}
