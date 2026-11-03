<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAddToBill;
use App\Models\Chef;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\Supply;
use App\Models\UsageIngredient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use App\Traits\MonitorsExecution;

class DeliveryBillsController extends Controller
{
    use MonitorsExecution;

    public function addItemToBill(Request $request, int $categoryId) //بدون تحسين
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::first();

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $quantity = $validated['quantity'];
        $menuItemId = $validated['menu_item_id'];
        $note = $validated['note'] ?? null;

        $Category = MenuCategory::find($categoryId);

        if (!$Category) {
            return response()->json(['message' => 'عذراً هذا النوع غير موجود'], 404);
        }

        $menuItem = MenuItem::where('id', $menuItemId)
            ->where('menu_category_id', $categoryId)
            ->first();

        if (!$menuItem) {
            return response()->json(['error' => 'هذه الوجبة لا تنتمي للفئة المحددة.'], 400);
        }

        if (!$menuItem->is_available) {
            return response()->json(['error' => 'هذه الوجبة غير متاحة حاليًا.'], 400);
        }

        try {

            //  بدون transaction ممكن يصير Race Condition
            //  بدون lock  ممكن يصير تضارب بيانات

            // 1) جلب المكونات
            $ingredients = UsageIngredient::where('menu_item_id', $menuItemId)->get();

            // 2) جلب المخزون بدون قفل
            $supplies = Supply::whereIn('id', $ingredients->pluck('supply_id'))->get()->keyBy('id');

            // 3) التحقق من الكمية
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
                    throw ValidationException::withMessages([
                        'stock' => 'المخزون غير كافي'
                    ]);
                }
            }

            if (!empty($insufficient)) {
                return response()->json([
                    'error' => 'عذرًا، لا تتوفر كمية كافية من بعض المكونات.',
                    'details' => $insufficient
                ], 422);
            }
            sleep(5);
            // 4) خصم المخزون (غير آمن)
            foreach ($ingredients as $ing) {
                $needed = $ing->quantity_required_per_meal * $quantity;
                $supply = $supplies[$ing->supply_id];
                $supply->quantity -= $needed;
                $supply->save();
}
            // 5) الحصول على الطلب
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

            // 6) إضافة عنصر
            $orderItem = new OrderItem();
            $orderItem->delivery_order_id = $order->id;
            $orderItem->menu_item_id = $menuItemId;
            $orderItem->status = 'pending';
            $orderItem->quantity = $quantity;
            $orderItem->order_type = 'delivery';
            $orderItem->notes = $note;
            $orderItem->save();

            // 7) إعادة حساب السعر (مكلف)
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

            // 8) إضافة نقاط الولاء (بدون lock)
            $giftPoints = ($menuItem->gifted_loyalty_points ?? 0) * $quantity;
            if ($giftPoints > 0) {
                $customer->loyalty_points += $giftPoints;
                $customer->save();
            }

            $customer->refresh();

            $totalPoints = $order->orderItems
                ->where('status', '!=', 'cancelled')
                ->sum(function ($item) {
                    return $item->menuItem->meal_price_in_points * $item->quantity;
                });

            return response()->json([
                'message' => 'تمت الإضافة بنجاح',
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

        } catch (\Exception $e) {

            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function addItemToBill2(Request $request, int $categoryId)
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = auth()->user()->customer ?? User::first();
        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $quantity   = $validated['quantity'];
        $menuItemId = $validated['menu_item_id'];
        $note       = $validated['note'] ?? null;

        return $this->monitorExecution('ADD_ITEM_OPTIMIZED', function () use ($customer, $categoryId, $menuItemId, $quantity, $note) {

            // هنا نبدأ الترانزاكشن
            $order = DB::transaction(function () use ($customer, $menuItemId, $quantity, $note, $categoryId) {

                // 1) جلب المكوّنات
                $ingredients = UsageIngredient::where('menu_item_id', $menuItemId)->get();
                $supplyIds = $ingredients->pluck('supply_id');

                // 2) Lock
                $supplies = Supply::whereIn('id', $supplyIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // 3) التحقق من الكميات
                foreach ($ingredients as $ing) {
                    $needed = $ing->quantity_required_per_meal * $quantity;
                    $supply = $supplies[$ing->supply_id];

                    if ($supply->quantity < $needed) {
                        throw new \Exception("Stock not enough");
                    }
                }

                // 4) خصم الكميات
                foreach ($ingredients as $ing) {
                    $needed = $ing->quantity_required_per_meal * $quantity;
                    $supplies[$ing->supply_id]->decrement('quantity', $needed);
                }

                // 5) جلب أو إنشاء الطلب
                $order = DeliveryOrder::where('customer_id', $customer->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    $order = DeliveryOrder::create([
                        'customer_id' => $customer->id,
                        'status' => 'pending',
                        'delivery_address' => '',
                        'total_price' => 0,
                    ]);
                }

                // 6) إضافة العنصر
                OrderItem::create([
                    'delivery_order_id' => $order->id,
                    'menu_item_id' => $menuItemId,
                    'status' => 'pending',
                    'quantity' => $quantity,
                    'order_type' => 'delivery',
                    'notes' => $note
                ]);

                // 7) تحديث السعر
                $menuItem = MenuItem::find($menuItemId);
                $order->increment('total_price', $menuItem->price * $quantity);

                // 8) نقاط الولاء
                Customer::where('id', $customer->id)
                    ->lockForUpdate()
                    ->increment(
                        'loyalty_points',
                        $menuItem->gifted_loyalty_points * $quantity
                    );

                return $order->fresh(['orderItems.menuItem']);
            });

            return response()->json([
                'message' => 'تمت الإضافة بنجاح',
                'order' => $order
            ], 201);

        }, [
            'user_id' => $customer->id,
            'category_id' => $categoryId,
            'menu_item_id' => $menuItemId,
            'quantity' => $quantity,
        ]);
    }


    public function addItemToBill3(Request $request, int $categoryId)
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = auth()->user()->customer;
        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        return $this->monitorExecution('QUEUE_REQUEST', function () use ($customer, $validated, $categoryId) {

            ProcessAddToBill::dispatch(
                $customer->id,
                $validated['menu_item_id'],
                $validated['quantity'],
                $validated['note'] ?? null,
                $categoryId
            );

            return response()->json([
                'message' => 'تم إرسال الطلب للمعالجة (Async)'
            ], 202);

        }, [
            'user_id' => $customer->id,
            'category_id' => $categoryId,
            'menu_item_id' => $validated['menu_item_id'],
            'quantity' => $validated['quantity'],
        ]);
    }

}
