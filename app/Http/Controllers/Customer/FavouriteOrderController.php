<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Favourite;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\Supply;
use App\Models\UsageIngredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FavouriteOrderController extends Controller
{
    public function addToFavourites(Request $request)
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $validated = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
        ]);

        $existing = Favourite::where('customer_id', $customer->id)
            ->where('menu_item_id', $validated['menu_item_id'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'هذه الوجبة موجودة بالفعل في المفضلة'], 200);
        }

        Favourite::firstOrCreate([
            'customer_id' => $customer->id,
            'menu_item_id' => $request->menu_item_id
        ]);

        $favourites = MenuItem::whereIn('id', function($query) use ($customer) {
            $query->select('menu_item_id')
                ->from('favourites')
                ->where('customer_id', $customer->id);
        })->get();

        return response()->json([
            'message' => 'تمت إضافة الوجبة إلى المفضلة بنجاح',
            'favourite' => $favourites
        ], 201);
    }

    public function removeFromFavourite(Request $request)
    {
        $request->validate([
            'favourite_item_id' => ['required', 'exists:favourites,id']
        ]);

        $customer = auth()->user()->customer;

        $favourite = Favourite::where('id', $request->favourite_item_id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$favourite) {
            return response()->json([
                'message' => 'هذه الوجبة غير موجودة في المفضلة.'
            ], 404);
        }

        Favourite::where('id', $request->favourite_item_id)
            ->where('customer_id', $customer->id)
            ->delete();

        $favourites = MenuItem::whereIn('id', function($query) use ($customer) {
            $query->select('menu_item_id')
                ->from('favourites')
                ->where('customer_id', $customer->id);
        })->get();

        return response()->json([
            'message' => 'تمت إزالة الوجبة من المفضلة بنجاح',
            'favourite' => $favourites
        ]);
    }

    public function addFavouriteItemToBill(Request $request)
    {
        $validated = $request->validate([
            'favourite_item_id' => ['required', 'exists:favourites,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $favourite = Favourite::where('id', $validated['favourite_item_id'])
            ->where('customer_id', $customer->id)
            ->with('menuItem')
            ->first();

        if (!$favourite) {
            return response()->json(['error' => 'هذه الوجبة غير موجودة في المفضلة.'], 404);
        }

        $menuItem = $favourite->menuItem;

        if (!$menuItem) {
            return response()->json(['error' => 'الوجبة غير متاحة.'], 404);
        }

        if (!$menuItem->is_available) {
            return response()->json(['error' => 'هذه الوجبة غير متاحة حالياً.'], 400);
        }

        $quantity = $validated['quantity'];
        $note = $validated['note'];
        $menuItemId = $menuItem->id;
        $categoryId = $menuItem->menu_category_id;

        try {
            $order = DB::transaction(function () use ($customer, $menuItem, $menuItemId, $quantity, $note, $categoryId) {

                $ingredients = UsageIngredient::where('menu_item_id', $menuItemId)->get();
                $supplyIds = $ingredients->pluck('supply_id')->all();

                $supplies = $supplyIds
                    ? Supply::whereIn('id', $supplyIds)->lockForUpdate()->get()->keyBy('id')
                    : collect();

                $insufficient = [];
                foreach ($ingredients as $ing) {
                    $needed = $ing->quantity_required_per_meal * $quantity;
                    $supply = $supplies[$ing->supply_id] ?? null;

                    if (!$supply || $supply->quantity < $needed) {
                        $insufficient[] = [
                            'ingredient_id' => $ing->supply_id,
                            'needed' => $needed,
                            'available' => $supply?->quantity ?? 0,
                        ];
                    }
                }

                if (!empty($insufficient)) {
                    throw ValidationException::withMessages([
                        'stock' => 'عذرًا، لا تتوفر كمية كافية من بعض المكونات لهذه الوجبة.',
                        'details' => $insufficient,
                    ]);
                }

                foreach ($ingredients as $ing) {
                    $needed = $ing->quantity_required_per_meal * $quantity;
                    $supply = $supplies[$ing->supply_id];
                    $supply->quantity -= $needed;
                    $supply->save();
                }

                $order = DeliveryOrder::where('customer_id', $customer->id)
                    ->where('status', 'pending')
                    ->first();

                if (!$order) {
                    $order = new DeliveryOrder();
                    $order->customer_id = $customer->id;
                    $order->status = 'pending';
                    $order->delivery_address = '';
                    $order->total_price = 0;
                    $order->save();
                }

                $orderItem = new OrderItem();
                $orderItem->delivery_order_id = $order->id;
                $orderItem->menu_item_id = $menuItemId;
                $orderItem->status = 'pending';
                $orderItem->quantity = $quantity;
                $orderItem->order_type = 'delivery';
                $orderItem->notes = $note ?? null;
                $orderItem->save();

                $orderItemsForTotal = $order->orderItems()
                    ->where('status', '!=', 'cancelled')
                    ->with('menuItem')
                    ->get();

                $total = 0;
                foreach ($orderItemsForTotal as $item) {
                    $total += $item->quantity * $item->menuItem->price;
                }

                $order->total_price = $total;
                $order->save();

                $giftPoints = ($menuItem->gifted_loyalty_points ?? 0) * $quantity;
                if ($giftPoints > 0) {
                    Customer::where('id', $customer->id)
                        ->lockForUpdate()
                        ->increment('loyalty_points', $giftPoints);
                }

                return $order->fresh(['orderItems.menuItem']);
            });

            $customer->refresh();

            $totalPoints = $order->orderItems
                ->where('status', '!=', 'cancelled')
                ->sum(function ($item) {
                    return $item->menuItem->meal_price_in_points * $item->quantity;
                });

            return response()->json([
                'message' => 'تمت إضافة الوجبة من المفضلة إلى الفاتورة بنجاح',
                'order' => [
                    'id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'status' => $order->status,
                    'total_price' => $order->total_price,
                    'total_price_in_points' => $totalPoints,
                    'customer_loyalty_points' => $customer->loyalty_points,
                    'delivery_address' => $order->delivery_address,
                    'order_items' => $order->orderItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'delivery_order_id' => $item->delivery_order_id,
                            'menu_item_id' => $item->menu_item_id,
                            'name' => $item->menuItem->name,
                            'price' => $item->menuItem->price,
                            'status' => $item->status,
                            'notes' => $item->notes,
                            'quantity' => $item->quantity,
                            'order_type' => $item->order_type,
                            'meal_price_in_points_for_quantity_required' => $item->menuItem->meal_price_in_points * $item->quantity,
                            'gifted_loyalty_points_for_quantity_required' => $item->menuItem->gifted_loyalty_points * $item->quantity,
                        ];
                    }),
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getFavourites()
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $favourites = Favourite::where('customer_id', $customer->id)
            ->with(['menuItem' => function ($query) {
                $query->select('id', 'name', 'price', 'is_available', 'meal_price_in_points', 'gifted_loyalty_points', 'image');
            }])
            ->get();

        if ($favourites->isEmpty()) {
            return response()->json(['message' => 'لا توجد وجبات مفضلة.'], 200);
        }

        $data = $favourites->map(function ($fav) {
            return [
                'favourite_id' => $fav->id,
                'menu_item_id' => $fav->menu_item_id,
                'name' => $fav->menuItem->name ?? null,
                'price' => $fav->menuItem->price ?? null,
                'is_available' => $fav->menuItem->is_available ?? false,
                'meal_price_in_points' => $fav->menuItem->meal_price_in_points ?? 0,
                'gifted_loyalty_points' => $fav->menuItem->gifted_loyalty_points ?? 0,
                'image' => $fav->menuItem->image ?? null,
            ];
        });

        return response()->json([
            'favourites' => $data
        ], 200);
    }




}
