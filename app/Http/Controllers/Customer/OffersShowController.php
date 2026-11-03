<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Offer;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class OffersShowController extends Controller
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

    public function puyOffer(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|exists:offers,id',
            'payment_method' => 'required|in:points,balance',
            'delivery_address' => 'required|string',
        ]);

        $user = auth()->user();
        $customer = $user->customer;

        // جلب العرض
        $offer = Offer::with('menuItems')->findOrFail($request->offer_id);

        $totalPriceInPoints = 0;
        foreach ($offer->menuItems as $item) {
            $totalPriceInPoints += $item->meal_price_in_points * $item->pivot->quantity;
        }

        // التحقق من طريقة الدفع
        if ($request->payment_method === 'points') {
            if ($customer->loyalty_points < $totalPriceInPoints) {
                return response()->json(['message' => 'النقاط غير كافية'], 400);
            }
            $customer->loyalty_points -= $totalPriceInPoints;
        } else {
            // هنا لازم يكون عندك حقل balance للرصيد إذا عم تستخدمه
            if ($customer->points_payment < $offer->offer_price) {
                return response()->json(['message' => 'الرصيد غير كافي'], 400);
            }
            $customer->points_payment -= $offer->offer_price;
        }

        $customer->save();

        // إنشاء فاتورة توصيل جديدة مرتبطة بالعرض
        $deliveryOrder = DeliveryOrder::create([
            'customer_id' => $customer->id,
            'offer_id' => $offer->id,
            'delivery_address' => $request->delivery_address,
            'status' => 'done',
            'total_price' => $offer->offer_price,
        ]);

        // جلب الوجبات من جدول menu_item_offer_pivots
        foreach ($offer->menuItems as $menuItem) {
            OrderItem::create([
                'menu_item_id' => $menuItem->id,
                'delivery_order_id' => $deliveryOrder->id,
                'chef_id' => 1, // ممكن توزعها حسب منطقك
                'quantity' => $menuItem->pivot->quantity,
                'status' => 'pending',
                'order_type' => 'delivery',
                'notes' => 'جزء من العرض',
                'offer_id' => $offer->id,
            ]);

            // نقص من المخزون (إذا عندك جدول مخزون)
            $menuItem->decrement('stock', $menuItem->pivot->quantity);
        }

        return response()->json([
            'message' => 'تم شراء العرض بنجاح',
            'order_id' => $deliveryOrder->id
        ], 201);
    }


}
