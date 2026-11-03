<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewsExperienceController extends Controller
{
    public function addReview(Request $request, int $deliveryOrderId)
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $validated = $request->validate([
            'food_rating' => 'required|integer|min:1|max:5',
            'service_rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $order = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('customer_id', $customer->id)
            ->where('status', 'done')
            ->first();

        if (!$order) {
            return response()->json(['error' => 'لا يمكن تقييم هذه الفاتورة'], 404);
        }

        $existingReview = Review::where('delivery_order_id', $order->id)->first();
        if ($existingReview) {
            return response()->json(['error' => 'تمت إضافة تقييم لهذه الفاتورة مسبقاً'], 409);
        }

        $review = Review::create([
            'delivery_order_id' => $order->id,
            'food_rating' => $validated['food_rating'],
            'service_rating' => $validated['service_rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم إضافة التقييم بنجاح',
            'review' => [
                'id' => $review->id,
                'delivery_order_id' => $review->delivery_order_id,
                'food_rating' => $review->food_rating,
                'service_rating' => $review->service_rating,
                'comment' => $review->comment,
            ]
        ], 201);
    }

    public function updateReview(Request $request, int $deliveryOrderId)
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        // التحقق من صحة البيانات
        $validated = $request->validate([
            'food_rating' => 'required|integer|min:1|max:5',
            'service_rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:255',
        ]);

        // جلب الفاتورة
        $order = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('customer_id', $customer->id)
            ->where('status', 'done')
            ->first();

        if (!$order) {
            return response()->json(['error' => 'الفاتورة غير موجودة أو لم يتم إنهاؤها'], 404);
        }

        $review = Review::where('delivery_order_id', $order->id)->first();

        if (!$review) {
            return response()->json(['error' => 'لا يوجد تقييم لتعديله'], 404);
        }

        $review->update($validated);

        return response()->json([
            'message' => 'تم تعديل التقييم بنجاح',
            'review' => [
                'delivery_order_id' => $review->delivery_order_id,
                'food_rating' => $review->food_rating,
                'service_rating' => $review->service_rating,
                'comment' => $review->comment,
            ]
        ], 200);
    }

    public function deleteReview(int $deliveryOrderId)
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json(['error' => 'غير مسجل حالياً'], 403);
        }

        $order = DeliveryOrder::where('id', $deliveryOrderId)
            ->where('customer_id', $customer->id)
            ->where('status', 'done')
            ->first();

        if (!$order) {
            return response()->json(['error' => 'الفاتورة غير موجودة أو لم يتم إنهاؤها'], 404);
        }

        $review = Review::where('delivery_order_id', $order->id)->first();

        if (!$review) {
            return response()->json(['error' => 'لا يوجد تقييم لحذفه'], 404);
        }

        $review->delete();

        return response()->json(['message' => 'تم حذف التقييم بنجاح'], 200);
    }


}
