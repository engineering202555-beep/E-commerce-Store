<?php

namespace App\Http\Controllers\Chef;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\Supply;
use App\Models\UsageIngredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChefController extends Controller
{
    public function getPendingOrders()
    {
        $chef = Auth::user()->chef;

        $orders = OrderItem::with(['menuItem', 'inOrder', 'deliveryOrder'])
            ->where('chef_id', $chef->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $orders
        ]);
    }

    public function getAllOrders()
    {
        $chef = Auth::user()->chef;

        $orders = OrderItem::with(['menuItem', 'inOrder', 'deliveryOrder'])
            ->where('chef_id', $chef->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $orders
        ]);
    }
    public function showOrderItem(OrderItem $orderItem)
    {
        $chef = Auth::user()->chef;

        if ($orderItem->chef_id !== $chef->id) {
            return response()->json([
                'message' => 'Order item not found or not assigned to this chef'
            ], 404);
        }

        $orderItem->load(['menuItem', 'inOrder', 'deliveryOrder']);

        return response()->json([
            'data' => $orderItem
        ]);
    }
    public function startPreparing(OrderItem $orderItem)
    {
        $chef = Auth::user()->chef;

        if ($orderItem->chef_id != $chef->id || $orderItem->status !== 'pending') {
            return response()->json([
                'message' => 'Invalid order item'
            ], 403);
        }

        // Check if there are enough ingredients
        $menuItem = $orderItem->menuItem;
        $ingredients = UsageIngredient::where('menu_item_id', $menuItem->id)->get();

        $insufficientIngredients = [];

        DB::beginTransaction();
        try {
            foreach ($ingredients as $ingredient) {
                $supply = Supply::find($ingredient->supply_id);
                $requiredQuantity = $ingredient->quantity_required_per_meal * $orderItem->quantity;

                if ($supply->quantity < $requiredQuantity) {
                    $insufficientIngredients[] = [
                        'name' => $supply->name,
                        'available' => $supply->quantity,
                        'required' => $requiredQuantity
                    ];
                }
            }

            if (count($insufficientIngredients) > 0) {
                // Cancel the order due to insufficient ingredients
                $orderItem->status = 'cancelled';
                $orderItem->notes = 'Cancelled due to insufficient ingredients';
                $orderItem->save();

                DB::commit();

                return response()->json([
                    'message' => 'Order cancelled due to insufficient ingredients',
                    'insufficient_ingredients' => $insufficientIngredients
                ], 400);
            }

            // Deduct ingredients from inventory
            foreach ($ingredients as $ingredient) {
                $supply = Supply::find($ingredient->supply_id);
                $requiredQuantity = $ingredient->quantity_required_per_meal * $orderItem->quantity;

                $supply->quantity -= $requiredQuantity;
                $supply->save();
            }

            // Update order status
            $orderItem->status = 'preparing';
            $orderItem->save();

            DB::commit();

            return response()->json([
                'message' => 'Order preparation started',
                'data' => $orderItem
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to start order preparation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function completeOrder(OrderItem $orderItem)
    {
        $chef = Auth::user()->chef;

        if ($orderItem->chef_id != $chef->id || $orderItem->status !== 'preparing') {
            return response()->json([
                'message' => 'Invalid order item'
            ], 403);
        }

        $orderItem->status = 'done';
        $orderItem->save();

        return response()->json([
            'message' => 'Order completed successfully',
            'data' => $orderItem
        ]);
    }

    public function updatePreparationTime(OrderItem $orderItem, Request $request)
    {
        $chef = Auth::user()->chef;

        $validator = Validator::make($request->all(), [
            'preparation_time' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($orderItem->chef_id != $chef->id || !in_array($orderItem->status, ['pending', 'preparing'])) {
            return response()->json([
                'message' => 'Invalid order item'
            ], 403);
        }

        $menuItem = MenuItem::find($orderItem->menu_item_id);
        $menuItem->preparation_time = $request->preparation_time;
        $menuItem->save();

        return response()->json([
            'message' => 'Preparation time updated successfully',
            'data' => $menuItem
        ]);
    }

    public function submitReport(Request $request)
    {
        $chef = Auth::user()->chef;

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $report = Report::create([
            'title' => $request->title,
            'body' => $request->body,
            'chef_id' => $chef->id
        ]);

        return response()->json([
            'message' => 'Report submitted successfully',
            'data' => $report
        ], 201);
    }
}
