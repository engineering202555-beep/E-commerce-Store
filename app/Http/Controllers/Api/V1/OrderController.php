<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
{
    $user = auth('jwt')->user();

    if ($user->hasRole('admin', 'web')) {

        $query = DeliveryOrder::query();

    } elseif ($user->hasRole('customer', 'web')) {

        $customer = $user->customer;

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found',
            ], 404);
        }

        $query = DeliveryOrder::where(
            'customer_id',
            $customer->id
        );

    } else {

        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to view delivery orders',
        ], 403);

    }

    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    $orders = $query
        ->with('orderItems.menuItem')
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'data' => $orders,
    ], 200);
}

  public function show(DeliveryOrder $order)
{
    $user = auth('jwt')->user();

    if ($user->hasRole('customer', 'web')) {

        $customer = $user->customer;

        if (
            ! $customer ||
            (int) $order->customer_id !== (int) $customer->id
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);

        }

    } elseif (! $user->hasRole('admin', 'web')) {

        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to view this order',
        ], 403);

    }

    $order->load('orderItems.menuItem');

    return response()->json([
        'success' => true,
        'data' => $order,
    ], 200);
}

    public function store(Request $request)
    {
        $request->validate([
            'delivery_address' => ['required', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.menu_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:menu_items,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $user = auth('jwt')->user();
        $customer = $user->customer;

        if ($user->role !== 'customer' || ! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Only customers can create delivery orders',
            ], 403);
        }

        $order = DB::transaction(function () use ($request, $customer) {
            $items = collect($request->input('items'));

            $productIds = $items->pluck('menu_item_id')->all();

            $products = MenuItem::query()
                ->whereIn('id', $productIds)
                ->where('is_available', true)
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                abort(422, 'One or more products are unavailable');
            }

            $total = 0;

            foreach ($items as $item) {
                $product = $products->get($item['menu_item_id']);

                $total += $product->price * $item['quantity'];
            }

            $order = DeliveryOrder::create([
                'customer_id' => $customer->id,
                'status' => 'pending',
                'total_price' => $total,
                'delivery_address' => $request->delivery_address,
            ]);
            foreach ($items as $item) {
                $order->orderItems()->create([
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                    'order_type' => 'delivery',
                    'status' => 'pending',
                ]);
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order->load('orderItems.menuItem'),
        ], 201);
    }

    public function updateStatus(Request $request, DeliveryOrder $order)
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['pending', 'done']),
            ],
        ]);

        $order->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->fresh(),
        ], 200);
    }
}