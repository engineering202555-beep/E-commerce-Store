<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TableLayout;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReservationsCustomerController extends Controller
{
    public function bookingTable(Request $request)
    {
        $request->validate([
            'table_id' => 'required|exists:table_layouts,id',
            'number_of_people' => 'required|integer|min:1',
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'notes' => 'nullable|string',
        ]);

        $customer = auth()->user()->customer;

        if ($customer->banned_until && now()->lt($customer->banned_until)) {
            return response()->json([
                'success' => false,
                'message' => 'تم حظرك من الحجز بسبب تكرار عدم الحضور. يمكنك الحجز بعد: ' . Carbon::parse($customer->banned_until)->format('Y-m-d H:i'),
            ], 403);
        }

        $table = TableLayout::findOrFail($request->table_id);

        if ($table->status === 'busy') {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، الطاولة مشغولة حالياً بزبائن في المطعم ولا يمكن حجزها الآن.'
            ], 422);
        }

        // ✅ 1. تحقق من عدد الكراسي
        if ($request->number_of_people > $table->number_of_chairs) {
            $suggestedTables = TableLayout::where('number_of_chairs', '>=', $request->number_of_people)
                ->where('status', 'empty')
                ->get(['id', 'number_of_chairs', 'status']);

            if ($suggestedTables->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، لا توجد طاولات فارغة مناسبة لعدد الأشخاص المطلوب.'
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'عدد الأشخاص أكبر من الكراسي في هذه الطاولة.',
                'suggested_tables' => $suggestedTables
            ], 422);
        }

        $startTime = Carbon::parse($request->start_at);
        $endTime = Carbon::parse($request->end_at);

        if ($startTime->diffInMinutes($endTime) < 30) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، يجب أن تكون مدة الحجز 30 دقيقة على الأقل.'
            ], 422);
        }

        if ($startTime->toDateString() !== $endTime->toDateString()) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، الحجز مسموح فقط لنفس اليوم ضمن ساعات عمل المطعم.'
            ], 422);
        }

        $openTime = $startTime->copy()->setTime(10, 0);
        $closeTime = $startTime->copy()->setTime(23, 59);

        if ($startTime->lessThan($openTime) || $endTime->greaterThan($closeTime)) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، المطعم يعمل من الساعة 10 صباحاً حتى 12 منتصف الليل فقط.'
            ], 422);
        }

        if ($endTime->lessThanOrEqualTo($startTime)) {
            return response()->json([
                'success' => false,
                'message' => 'وقت النهاية يجب أن يكون بعد وقت البداية.'
            ], 422);
        }

        $check = $this->getAvailableSlotsForTable($table->id, $startTime, $endTime);

        $overlapping = false;
        $gapMinutes = 10;

        foreach ($check['reservations'] as $res) {
            $resStart = Carbon::parse($res->start_at)->subMinutes($gapMinutes);
            $resEnd = Carbon::parse($res->end_at)->addMinutes($gapMinutes);

            if ($startTime->lt($resEnd) && $endTime->gt($resStart)) {
                $overlapping = true;
                break;
            }
        }

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'الفترة التي طلبتها متداخلة مع حجوزات أخرى.',
                'available_slots' => $check['availableSlots']
            ], 422);
        }

// ✅ إذا ما في تداخل → أنشئ الحجز
        $reservation = Reservation::create([
            'customer_id' => $customer->id,
            'table_layout_id' => $table->id,
            'start_at' => $startTime->format('Y-m-d H:i'),
            'end_at' => $endTime->format('Y-m-d H:i'),
            'number_of_people' => $request->number_of_people,
            'notes' => $request->notes,
            'status' => 'reserved'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم الحجز بنجاح ✅',
            'reservation' => $reservation->only(['id', 'customer_id', 'table_layout_id', 'start_at', 'end_at', 'number_of_people', 'notes', 'status']),
            'note' => "احفظ رقم حجزك {$reservation->id} واعطه للنادل عند قدومك لمطعمنا",
        ],201);
    }

    public function cancelMyReservation($reservationId)
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد عميل مسجل حالياً.'
            ], 403);
        }

        $reservation = Reservation::find($reservationId);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'الحجز غير موجود.'
            ], 404);
        }

        if ($reservation->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'الحجز لا يخصك',
            ], 403);
        }

        // يُسمح إلغاؤه فقط إذا كان بالحالة reserved
        if ($reservation->status !== 'reserved') {
            return response()->json(['success' => false, 'message' => 'لم يعد ممكن إلغاء هذا الحجز في وضعه الحالي.'], 422);
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_by' => 'customer',
            'cancelled_at' => Carbon::now(),
        ]);

        $table = $reservation->tableLayout;
        if ($table && $table->status === 'reserved') {
            $table->update(['status' => 'empty']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الحجز بنجاح.',
            'reservation_id' => $reservation->id,
        ]);
    }

    public function updateMyReservation(Request $request, $reservationId)
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد عميل مسجل حالياً.'
            ], 403);
        }

        $reservation = Reservation::find($reservationId);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'الحجز غير موجود.'
            ], 404);
        }

        if ($reservation->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'الحجز لا يخصك',
            ], 403);
        }

        // ✅ 2. تحقق إنو الحجز لساتو "محجوز"
        if ($reservation->status !== 'reserved') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل هذا الحجز لأنه لم يعد في حالة محجوز.'
            ], 422);
        }

        if (now()->gte(Carbon::parse($reservation->start_at)->subMinutes(10))) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل الحجز قبل 10 دقائق من موعده الطاولة أصبحت محجوزة.'
            ], 422);
        }

        // ✅ 3. تحقق إنو ما مرّت نص ساعة عموعد الحجز
        if (now()->gte(Carbon::parse($reservation->start_at)->addMinutes(30))) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل الحجز بعد مرور 30 دقيقة من موعده.لفد تم إلغاؤه من قبل النظام .'
            ], 422);
        }

        // ✅ 4. Validate البيانات الجديدة
        $request->validate([
            'table_id' => 'nullable|exists:table_layouts,id',
            'number_of_people' => 'nullable|integer|min:1',
            'start_at' => 'nullable|date|after:now',
            'end_at' => 'nullable|date|after:start_at',
            'notes' => 'nullable|string',
        ]);

        $startTime = $request->filled('start_at') ? Carbon::parse($request->start_at) : Carbon::parse($reservation->start_at);
        $endTime = $request->filled('end_at') ? Carbon::parse($request->end_at) : Carbon::parse($reservation->end_at);


        // ✅ 5. إذا بدو يغيّر الطاولة
        if ($request->filled('table_id')) {

            $newTable = TableLayout::findOrFail($request->table_id);
            $numberOfPeople = $request->filled('number_of_people') ? $request->number_of_people : $reservation->number_of_people;

            if ($newTable->status === 'busy') {
                return response()->json(['success' => false, 'message' => 'الطاولة المختارة غير متاحة حالياً.'], 422);
            }

            if($numberOfPeople>$newTable->number_of_chairs){

                $suggestedTables = TableLayout::where('number_of_chairs', '>=', $numberOfPeople)
                    ->where('status', 'empty')
                    ->get(['id', 'number_of_chairs', 'status']);

                return response()->json([
                    'success' => false,
                    'message'=>'عذراً عدد الأشخاص الذين تصطحبهم في الحجز لا يناسب هذه الطاولة',
                    'suggested_tables' => $suggestedTables
                ]);
            }

            $check = $this->getAvailableSlotsForTable($newTable->id, $startTime, $endTime, $reservation->id);

            $overlapping = false;
            $gapMinutes = 10;

            foreach ($check['reservations'] as $res) {
                $resStart = Carbon::parse($res->start_at)->subMinutes($gapMinutes);
                $resEnd = Carbon::parse($res->end_at)->addMinutes($gapMinutes);

                if ($startTime->lt($resEnd) && $endTime->gt($resStart)) {
                    $overlapping = true;
                    break;
                }
            }

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'الفترة التي طلبتها متداخلة مع حجوزات أخرى.',
                    'available_slots' => $check['availableSlots']
                ], 422);
            }

            $reservation->table_layout_id = $newTable->id;
        }

        $table=$reservation->tableLayout;

        if ($request->filled('number_of_people')) {
            if ($request->number_of_people > $table->number_of_chairs) {

                $suggestedTables = TableLayout::where('number_of_chairs', '>=', $request->number_of_people)
                    ->where('status', 'empty')
                    ->get(['id', 'number_of_chairs', 'status']);

                if ($suggestedTables->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'عذراً، لا توجد طاولات فارغة مناسبة لعدد الأشخاص المطلوب.'
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'عدد الأشخاص أكبر من الكراسي في هذه الطاولة.',
                    'suggested_tables' => $suggestedTables
                ], 422);
            }
            // ✅ إذا مر التحقق، عدل العدد
            $reservation->number_of_people = (int)$request->number_of_people;
        }

        // ✅ 6. إذا بدو يغير الوقت
        if ($request->filled('start_at') && $request->filled('end_at')) {

            $check = $this->getAvailableSlotsForTable($reservation->table_layout_id, $startTime, $endTime, $reservation->id);

            if ($startTime->toDateString() !== $endTime->toDateString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'الحجز يجب أن يكون في نفس اليوم.'
                ], 422);
            }

            // ساعات العمل
            $openTime = $startTime->copy()->setTime(10, 0);
            $closeTime = $startTime->copy()->setTime(23, 59);

            if ($startTime->lt($openTime) || $endTime->gt($closeTime)) {
                return response()->json([
                    'success' => false,
                    'message' => 'المطعم يعمل من الساعة 10 صباحاً حتى 12 منتصف الليل فقط.'
                ], 422);
            }

            if ($startTime->diffInMinutes($endTime) <= 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، يجب أن تكون مدة الحجز 30 دقيقة على الأقل.'
                ], 422);
            }

            $overlapping = false;
            $gapMinutes = 10;

            foreach ($check['reservations'] as $res) {
                $resStart = Carbon::parse($res->start_at)->subMinutes($gapMinutes);
                $resEnd = Carbon::parse($res->end_at)->addMinutes($gapMinutes);

                if ($startTime->lt($resEnd) && $endTime->gt($resStart)) {
                    $overlapping = true;
                    break;
                }
            }

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'الفترة التي طلبتها متداخلة مع حجوزات أخرى.',
                    'available_slots' => $check['availableSlots']
                ], 422);
            }

            $reservation->start_at = $startTime->format('Y-m-d H:i');
            $reservation->end_at = $endTime->format('Y-m-d H:i');

        }

        if ($request->filled('notes')) {
            $reservation->notes = $request->notes;
        }

        $reservation->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل الحجز بنجاح ✅',
            'reservation' => [
                'id' => $reservation->id,
                'table_layout_id' => $reservation->table_layout_id,
                'start_at' => $reservation->start_at->format('Y-m-d H:i'),
                'end_at' => $reservation->end_at->format('Y-m-d H:i'),
                'number_of_people' => (int) $reservation->number_of_people,
                'notes' => $reservation->notes,
                'status' => $reservation->status,
            ],
            'note' => " يبقى رقم حجزك هو {$reservation->id}",
        ]);

    }

    private function getAvailableSlotsForTable($tableId, Carbon $startTime, Carbon $endTime, $excludeReservationId = null, $gapMinutes = 10)
    {
        // 1. تحديد بداية ونهاية اليوم (حسب المطعم)
        $dayStart = $startTime->copy()->startOfDay()->setTime(10, 0);  // المطعم يفتح 10 صباحاً
        $dayEnd = $startTime->copy()->endOfDay()->setTime(23, 59);    // يغلق منتصف الليل

        if ($startTime->isToday()) {
            // إذا كان الحجز لليوم الحالي نبدأ من الوقت الحالي إذا كان بعد 10:00
            $now = Carbon::now();
            if ($now->gt($dayStart)) {
                $dayStart = $now->copy();
            }
        }

        // 2. جلب كل الحجوزات لهذا اليوم للطاولة
        $existingReservations = Reservation::where('table_layout_id', $tableId)
            ->whereIn('status', ['reserved', 'arrived'])
            ->when($excludeReservationId, function ($query) use ($excludeReservationId) {
                $query->where('id', '!=', $excludeReservationId);
            })
            ->whereDate('start_at', $dayStart->toDateString())
            ->orderBy('start_at')
            ->get();

        $availableSlots = [];
        $pointer = $dayStart->copy();

        foreach ($existingReservations as $res) {
            $resStart = Carbon::parse($res->start_at)->subMinutes($gapMinutes);
            $resEnd = Carbon::parse($res->end_at)->addMinutes($gapMinutes);

            // إذا في مساحة قبل الحجز الحالي
            if ($pointer->lt($resStart)) {
                $slotEnd = $resStart->copy();
                if ($pointer->diffInMinutes($slotEnd) >= 30) {
                    $availableSlots[] = [
                        'from' => $pointer->format('Y-m-d H:i'),
                        'to' => $slotEnd->format('Y-m-d H:i')
                    ];
                }
            }

            if ($pointer->lt($resEnd)) {
                $pointer = $resEnd->copy();
            }
        }

        if ($pointer->lt($dayEnd)) {
            if ($pointer->diffInMinutes($dayEnd) >= 30) {
                $availableSlots[] = [
                    'from' => $pointer->format('Y-m-d H:i'),
                    'to' => $dayEnd->format('Y-m-d H:i')
                ];
            }
        }

        return [
            'reservations' => $existingReservations,
            'availableSlots' => $availableSlots
        ];
    }

    public function reservationsArchive()
    {
        $customer = auth()->user()->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد عميل مسجل حالياً.'
            ], 403);
        }

        $isBanned = $customer->banned_until && now()->lt($customer->banned_until);
        $banMessage = $isBanned
            ? "أنت محظور حتى " . Carbon::parse($customer->banned_until)->format('Y-m-d H:i')
            : null;

        $reservations = Reservation::where('customer_id', $customer->id)
            ->orderBy('start_at', 'desc')
            ->get()
            ->map(function ($res) {
                return [
                    'id' => $res->id,
                    'table_id' => $res->table_layout_id,
                    'start_at' => $res->start_at->format('Y-m-d H:i'),
                    'end_at' => $res->end_at->format('Y-m-d H:i'),
                    'number_of_people' => (int) $res->number_of_people,
                    'status' => $res->status,
                    'cancelled_by' => $res->cancelled_by,
                    'cancel_reason' => $res->status === 'cancelled'
                        ? ($res->cancelled_by === 'system'
                            ? 'تم الإلغاء تلقائياً بسبب عدم الحضور'
                            : 'تم الإلغاء من قبلك')
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => $banMessage,
            'is_banned' => $isBanned,
            'banned_until' => $isBanned
                ? Carbon::parse($customer->banned_until)->format('Y-m-d H:i')
                : null,
            'no_show_count' => $customer->no_show_count,
            'reservations' => $reservations
        ]);
    }




}
