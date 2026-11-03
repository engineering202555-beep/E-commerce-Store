<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\Chef;
use App\Models\InOrder;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\TableLayout;
use App\Models\UsageIngredient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class BillController extends Controller
{

    private function getNextChefForInOrder($menuCategoryId)
    {
        $activeUserIds = PersonalAccessToken::where('tokenable_type', 'App\Models\User')
            ->pluck('tokenable_id')
            ->unique()
            ->toArray(); //whose online

        $activeChefIds = User::role('chef')
            ->whereIn('id', $activeUserIds)
            ->pluck('id')
            ->toArray(); //just chefs from users

        $chefs = User::role('chef')
            ->whereHas('chef', function ($q) use ($menuCategoryId) { //whose cooking this type of food
                $q->where('menu_category_id', $menuCategoryId);
            })
            ->whereIn('id', $activeChefIds)
            ->get()
            ->values();//transform to group by index
        /*debug:
        info('Active User IDs:', $activeUserIds);
        info('Active Chef IDs:', $activeChefIds);
        info('Chefs:', $chefs->toArray());*/

        if ($chefs->isEmpty()) {
            throw new \Exception("No available chefs for this category right now.");
        }

        $lastOrderItem = OrderItem::whereHas('menuItem', function($q) use ($menuCategoryId) {
            $q->where('menu_category_id', $menuCategoryId);
        })->latest()->first();//last order item from its type

        $lastChefId = null;
        if ($lastOrderItem) {
            $lastChefId = $lastOrderItem->chef_id; //and who is chef done it
        }

        $lastChefIndex = false;
        if ($lastChefId) {
            $lastChefIndex = $chefs->search(function($chef)use ($lastChefId) {
                return $chef->id === $lastChefId; //return his index
            });
        }

        if ($lastChefIndex !== false) {
            $nextChefIndex = ($lastChefIndex + 1) % $chefs->count(); //go to the next (ROUND-ROBIN)
        } else {
            $nextChefIndex = 0;
        }

        return $chefs[$nextChefIndex];
    }

    public function addOrderItemToInOrder(Request $request,$tableId,$menuCategoryId)
    {
        $waiter = auth()->user();

        if (!$waiter || !$waiter->hasRole('waiter')) {
            return response()->json(['error' => 'فقط النادل المسجل دخوله يمكنه تنفيذ هذه العملية.'], 401);
        }

        $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        $table = TableLayout::find($tableId);
        if (!$table) {
            return response()->json(['error' => 'الطاولة غير موجودة .'], 404);
        }

        if ($table->status !== 'busy') {
            return response()->json(['error' => 'لا يمكن إضافة طلب لطاولة غير مشغولة .'], 400);
        }

        $inOrder = InOrder::where('table_layout_id', $tableId)
            ->where('payment_completed', false)
            ->first();

        if (!$inOrder) {
            $inOrder = InOrder::create([
                'table_layout_id' => $tableId,
                'waiter_id' => $waiter->waiter->id,
                'total_price' => 0,
                'status' => 'pending'
            ]);
        }

        if ($inOrder->waiter_id !== auth()->user()->waiter->id) {
            return response()->json(['error' => 'غير مسموح لك بالتعديل على هذه الفاتورة.'], 403);
        }

        $menuItem = MenuItem::where('id', $request->menu_item_id)
            ->where('menu_category_id', $menuCategoryId)
            ->first();

        if (!$menuItem) {
            return response()->json(['error' => 'هذه الوجبة لا تنتمي للفئة المحددة.'], 400);
        }

        $usageIngredients = UsageIngredient::where('menu_item_id', $menuItem->id)->get();
        $insufficientSupplies = [];

        foreach ($usageIngredients as $usage) {
            $supply = $usage->supply;
            $totalQuantityNeeded = $usage->quantity_required_per_meal * $request->quantity;

            if ($supply->quantity < $totalQuantityNeeded) {
                $insufficientSupplies[] = [
                    'supply_name' => $supply->name,
                    'available_quantity' => $supply->quantity,
                    'needed_quantity' => $totalQuantityNeeded
                ];
            }
        }

        if (!empty($insufficientSupplies)) {
            return response()->json([
                'success' => false,
                'message' => 'عذرًا، لا يوجد كمية كافية لبعض المكونات لإعداد هذه الوجبة.',
                'details' => $insufficientSupplies
            ], 400);
        }

        $nextChef = $this->getNextChefForInOrder($menuCategoryId);

        foreach ($usageIngredients as $usage) {
            $supply = $usage->supply;
            $totalQuantityUsed = $usage->quantity_required_per_meal * $request->quantity;
            $supply->quantity -= $totalQuantityUsed;
            $supply->save();
        }

        $Price = $menuItem->price * $request->quantity;
        $inOrder->total_price += $Price;
        $inOrder->save();

        $orderItem = OrderItem::create([
            'menu_item_id' => $menuItem->id,
            'quantity' => $request->quantity,
            'notes' => $request->notes,
            'status' => 'pending',
            'in_order_id' => $inOrder->id,
            'order_type'=>'in',
            'chef_id' => $nextChef->chef->id
        ]);

        return response()->json([
            'message' => 'تمت إضافة الطلب إلى الفاتورة بنجاح..',
            'order_item' => [
                'order_item_id' => $orderItem->id,
                'quantity' => $orderItem->quantity,
                'notes' => $orderItem->notes,
                'status' => $orderItem->status,
                'in_order_id' => $inOrder->id,
                'chef_id' => $nextChef->id,
                'order_type' => $orderItem->order_type,
                'menu_item_id' => $orderItem->menu_item_id,
                'price_per_meal'=>$menuItem->price
            ],
            'new_total_price' => $inOrder->total_price
        ],200);
    }
    public function updateOrCancelOrderItem(Request $request, $inOrderId)
    {
        $request->validate([
            'order_item_id' => 'required',
            'new_quantity' => 'nullable|integer|min:1',
            'cancel' => 'nullable|boolean'
        ]);

        $hasNewQuantity = $request->input('new_quantity');
        $hasCancel = $request->boolean('cancel');

        $inOrder = InOrder::find($inOrderId);
        if (!$inOrder) {
            return response()->json(['error' => 'الفاتورة غير موجودة.'], 404);
        }
        if ($inOrder->payment_completed) {
            return response()->json(['error' => 'الفاتورة مدفوعة.'], 404);
        }
        if ($inOrder->waiter_id !== auth()->user()->waiter->id) {
            return response()->json(['error' => 'غير مسموح لك بالتعديل على هذه الفاتورة.'], 403);
        }
        $orderItem = OrderItem::where('id', $request->order_item_id)
            ->where('in_order_id', $inOrderId)
            ->first();

        if (!$orderItem) {
            return response()->json(['error' => 'الوجبة غير موجودة ضمن هذه الفاتورة.'], 404);
        }

        if ($orderItem->status !== 'pending') {
            return response()->json(['error' => 'لا يمكن تعديل أو إلغاء هذه الوجبة لأنها ليست بحالة pending.'], 400);
        }

        if (!$hasCancel && is_null($hasNewQuantity)) {
            return response()->json(['error' => 'يرجى تحديد إما new_quantity أو cancel فقط.'], 400);
        }

        if ($hasCancel && !is_null($hasNewQuantity)) {
            return response()->json(['error' => 'لا يمكن تنفيذ التعديل والإلغاء معًا. اختر أحد الخيارين فقط.'], 400);
        }

        $menuItem = MenuItem::find($orderItem->menu_item_id);
        $usageIngredients = UsageIngredient::where('menu_item_id', $menuItem->id)->get();
        $oldQuantity = $orderItem->quantity;

        if ($hasCancel) {
            foreach ($usageIngredients as $usage) {
                $supply = $usage->supply;
                $supply->quantity += $usage->quantity_required_per_meal * $oldQuantity;
                $supply->save();
            }

            $inOrder->total_price -= $menuItem->price * $oldQuantity;
            $inOrder->save();

            $orderItem->status = 'cancelled';
            $orderItem->save();

            return response()->json([
                'message' => 'تم إلغاء الطلب بنجاح.',
                'new_total_price' => $inOrder->total_price,

            ],200);
        }

            foreach ($usageIngredients as $usage) {
                $supply = $usage->supply;
                $supply->quantity += $usage->quantity_required_per_meal * $oldQuantity;
                $supply->save();
            }

        $insufficientSupplies = [];
        foreach ($usageIngredients as $usage) {
            $supply = $usage->supply;
            $neededQty = $usage->quantity_required_per_meal * $hasNewQuantity;

            if ($supply->quantity < $neededQty) {
                $insufficientSupplies[] = [
                    'supply_name' => $supply->name,
                    'available_quantity' => $supply->quantity,
                    'needed_quantity' => $neededQty
                ];
            }
        }
        if (!empty($insufficientSupplies)) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد كميات كافية لبعض المكونات.',
                'details' => $insufficientSupplies
            ], 400);
        }

        foreach ($usageIngredients as $usage) {
            $supply = $usage->supply;
            $supply->quantity -= $usage->quantity_required_per_meal * $hasNewQuantity;
            $supply->save();
        }

        $orderItem->quantity = $hasNewQuantity;
        $orderItem->save();

        $newTotalPrice = $inOrder->orderItems()
            ->whereNot('status', 'cancelled')
            ->get()
            ->sum(function ($item) {
                return $item->quantity * $item->menuItem->price;
            });

        $inOrder->total_price = $newTotalPrice;
        $inOrder->save();

        return response()->json([
            'message' => 'تم تعديل الكمية بنجاح.',
            'new_quantity' => $hasNewQuantity,
            'order_item' => [
                'order_item_id' => $orderItem->id,
                'status' => $orderItem->status,
                'in_order_id' => $inOrder->id,
                'order_type' => $orderItem->order_type,
                'menu_item_id' => $orderItem->menu_item_id,
                'price_per_meal'=>$menuItem->price
            ],
            'new_total_price' => $inOrder->total_price,
        ],200);
    }


    public function completedBill(Request $request)
    {
        $request->validate([
            'inOrderId' => 'required|integer',
        ]);

        $inOrder = InOrder::with(['tableLayout', 'orderItems'])->find($request->inOrderId);

        if (!$inOrder) {
            return response()->json(['error' => 'الفاتورة غير موجودة.'], 404);
        }

        if ($inOrder->payment_completed) {
            return response()->json(['message' => 'هذه الفاتورة مدفوعة مسبقًا.'], 400);
        }

        if ($inOrder->waiter_id !== auth()->user()->waiter->id) {
            return response()->json(['error' => 'غير مسموح لك بالتعديل على هذه الفاتورة.'], 403);
        }

        $pendingItems = $inOrder->orderItems->filter(function($item) {
            return !in_array($item->status, ['done', 'cancelled']);
        });

        if ($pendingItems->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إتمام الدفع، لا تزال هناك طلبات لم تُنجز أو تُلغى.',
                'pending_items' => $pendingItems->map(function($item) {
                    return [
                        'order_item_id' => $item->id,
                        'menu_item_id' => $item->menu_item_id,
                        'quantity' => $item->quantity,
                        'status' => $item->status,
                        'notes' => $item->notes
                    ];
                })
            ], 422);
        }

        $inOrder->payment_completed = true;
        $inOrder->save();

        $table = $inOrder->tableLayout;
        if ($table) {
            $activeReservation = $table->reservations()
                ->where('status', 'arrived')
                ->orderByDesc('start_at')
                ->first();

            if ($activeReservation) {
                $activeReservation->status = 'done';
                $activeReservation->save();
            }

            $table->status = 'empty';
            $table->save();
        }

        return response()->json([
            'message' => 'تمت عملية الدفع بنجاح، وتم تفريغ الطاولة.',
            'in_order_payment_completed' => $inOrder->payment_completed,
            'table_status' => $table->status
        ]);
    }

    public function getOrderPreparationAndWaitingTime($inOrderId)
    {
        $inOrder = InOrder::find($inOrderId);
        if (!$inOrder) {
            return response()->json(['error' => 'الفاتورة غير موجودة.'], 404);
        }

        if ($inOrder->waiter_id !== auth()->user()->waiter->id) {
            return response()->json(['error' => 'غير مسموح لك بالتعديل على هذه الفاتورة.'], 403);
        }

        // جميع الطلبات غير الملغاة والغير مدفوعة
        $allItems = OrderItem::whereHas('inOrder', function ($q) {
            $q->where('payment_completed', false);
        })
            ->where('status', '!=', 'cancelled')
            ->with(['menuItem', 'inOrder'])
            ->orderBy('created_at')
            ->get();

        if ($allItems->isEmpty()) {
            return response()->json([
                'order_id' => $inOrderId,
                'preparation_summary' => 'لا توجد طلبات.',
                'waiting_time_minutes' => 0,
                'delayed_items' => []
            ]);
        }

        // الطلبات الخاصة بهذه الفاتورة
        $thisOrderItems = $allItems->where('in_order_id', $inOrderId);

        if ($thisOrderItems->isEmpty()) {
            return response()->json(['error' => 'الفاتورة لا تحتوي طلبات صالحة.'], 400);
        }

        // أول طلب في الفاتورة
        $firstItem = $thisOrderItems->sortBy('created_at')->first();
        $firstItemTime = $firstItem->created_at;

        // الطلبات الأساسية = ≤ 15.0 دقيقة فرق عن أول طلب (بـ float)
        $mainItems = $thisOrderItems->filter(function ($item) use ($firstItemTime) {
            return $firstItemTime->floatDiffInMinutes($item->created_at) <= 15.0;
        });

        // أسماء الطلبات الأساسية
        $mainItemNames = $mainItems->map(fn($item) => $item->menuItem->name ?? 'غير معروف')->unique()->values()->toArray();

        // وقت التحضير الأساسي
        $mainPrepTime = $mainItems->sum(fn($item) => ($item->menuItem->preparation_time ?? 0) * $item->quantity);

        // وقت الانتظار من الطلبات السابقة لأوّل طلب بالفاتورة
        $waitingTime = $allItems
            ->filter(fn($item) => $item->created_at < $firstItemTime)
            ->sum(fn($item) => ($item->menuItem->preparation_time ?? 0) * $item->quantity);

        function formatMinutesToHoursAndMinutes($minutes) {
            if ($minutes < 60) {
                return $minutes . ' دقيقة';
            }
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return $hours . ':' . str_pad($remainingMinutes, 2, '0', STR_PAD_LEFT); // مثال: 1:05
        }

        // الطلبات المتأخرة
        $delayedItems = $thisOrderItems->filter(function ($item) use ($firstItemTime) {
            return $firstItemTime->floatDiffInMinutes($item->created_at) > 15.0;
        })->map(function ($item) use ($firstItemTime, $allItems) {
            $requestedAfter = $firstItemTime->floatDiffInMinutes($item->created_at);
            $prepTime = ($item->menuItem->preparation_time ?? 0) * $item->quantity;

            $priorItems = $allItems->filter(fn($other) => $other->created_at < $item->created_at);
            $individualWaiting = $priorItems->sum(fn($i) => ($i->menuItem->preparation_time ?? 0) * $i->quantity);

            return [
                'menu_item' => $item->menuItem->name ?? 'غير معروف',
                'requested_after_minutes' => formatMinutesToHoursAndMinutes(round($requestedAfter, 2)),
                'individual_preparation_time_minutes' => formatMinutesToHoursAndMinutes($prepTime),
                'individual_waiting_time_minutes' => formatMinutesToHoursAndMinutes($individualWaiting)
            ];
        })->values()->toArray();

        return response()->json([
            'order_id' => $inOrderId,
            'preparation_summary' => 'وقت تحضير الوجبات (' . implode('، ', $mainItemNames) . ') هو ' . formatMinutesToHoursAndMinutes($mainPrepTime),
            'waiting_time_minutes' => formatMinutesToHoursAndMinutes($waitingTime),
            'delayed_items' => $delayedItems
        ]);
    }

    public function getBillDetails(Request $request)
    {
        $request->validate([
            'in_order_id' => 'required|exists:in_orders,id',
        ]);

        $inOrder = InOrder::with('orderItems.menuItem', 'tableLayout')->find($request->in_order_id);

        if (!$inOrder) {
            return response()->json([
                'error' => 'الفاتورة غير موجودة.'
            ], 404);
        }

        if ($inOrder->waiter_id !== auth()->user()->waiter->id) {
            return response()->json([
                'error' => 'ليس لديك صلاحية لعرض هذه الفاتورة.'
            ], 403);
        }

        $items = $inOrder->orderItems->map(function ($item) {
            return [
                'order_item_id' => $item->id,
                'meal_name' => $item->menuItem->name ?? 'غير معروف',
                'quantity' => $item->quantity,
                'price_per_item' => $item->menuItem->price ?? 0,
                'total_price_for_item' => ($item->menuItem->price ?? 0) * $item->quantity,
                'status_for_item'=>$item->status
            ];
        });

        return response()->json([
            'bill_id' => $inOrder->id,
            'table_id' => $inOrder->tableLayout->id,
            'date' => $inOrder->created_at->format('Y-m-d H:i'),
            'total_price' => $inOrder->total_price,
            'items' => $items
        ]);
    }

}
