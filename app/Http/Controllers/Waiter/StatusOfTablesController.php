<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Reservation;
use App\Models\TableLayout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StatusOfTablesController extends Controller
{
    public function viewStatusOfTable()
    {
       $tables=TableLayout::all(['id','status','number_of_chairs']);
       return response()->json([
           'success' => true,
           'data' => $tables
       ],200);
    }
    public function openTable(Request $request,$tableId)
    {
        $validator = Validator::make($request->all(), [
            'make_busy' => ['nullable', 'boolean'],
            'make_arrived' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $validated = $validator->validated();
        $makeBusy = $validated['make_busy'] ?? 0;
        $makeArrived = $validated['make_arrived'] ?? 0;

        $table = TableLayout::with(['reservations' => function ($q) {
            $q->where('status', 'reserved')
               ->orderBy('start_at');
        }])->find($tableId);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'الطاولة غير موجودة.'
            ], 404);
        }

        $MenuCategories = MenuCategory::select('id', 'type_name', 'description')->get();

        if ($table->status === 'empty') {
            if ($makeBusy) {
                $table->status = 'busy';
                $table->save();

                return response()->json([
                    'success' => true,
                    'message' => 'تم تعيين الطاولة كمشغولة. عرض أنواع الطعام.',
                    'table_id' =>$table->id,
                    'food_types' => $MenuCategories,
                ],200);
            }

            if ($makeArrived) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تنفيذ make_arrived لطاولة فارغة.'
                ], 400);
            }
            return response()->json([
                'success' => true,
                'message' => 'الطاولة فارغة',
                'table_id' =>$table->id,
                'food_types' => $MenuCategories,
            ],200);
        }

        if ($table->status === 'busy') {
            return response()->json([
                'success' => false,
                'message' => 'الطاولة مشغولة حالياً.',
                'table_id' =>$table->id
            ], 409);
        }
        if ($table->status === 'reserved') {
        // and at all the table become reserved just before 10 minute
            //this return one table

            if ($makeBusy) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تحويل طاولة محجوزة إلى مشغولة مباشرة. استخدم make_arrived.'
                ], 400);
            }

            $now = now();
            $in10Min = $now->copy()->addMinutes(10);

            $reservation = $table->reservations()
                ->where(function ($q) use ($now, $in10Min) {
                    $q->whereBetween('start_at', [$now, $in10Min])
                        ->orWhere(function ($q2) use ($now) {
                            $q2->where('start_at', '<=', $now)
                                ->where('end_at', '>=', $now);
                        });
                })
                ->orderBy('start_at')
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد حجز نشط لهذه الطاولة حالياً.'
                ], 404);
            }

            if ($makeBusy) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تحويل طاولة محجوزة إلى مشغولة مباشرة. استخدم make_arrived.'
                ], 400);
            }

            if ($makeArrived) {
                $canArrive = $now->greaterThanOrEqualTo($reservation->start_at->copy()->subMinutes(10));
                if (!$canArrive) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن تنفيذ make_arrived قبل 10 دقائق من وقت الحجز.'
                    ], 400);
                }

                if ($makeArrived) {  // you can not do makeArrived before

                    $reservation->status = 'arrived';
                    $reservation->save();

                    $table->status = 'busy';
                    $table->save();

                    return response()->json([
                        'success' => true,
                        'message' => 'تم وصول الضيوف، الطاولة أصبحت مشغولة. عرض أنواع الطعام.',
                        'table_id' => $table->id,
                        'food_types' => $MenuCategories
                    ], 200);
                }
            }

            $timeDiffInMinutes = now()->diffInMinutes($reservation->time, false);
            $timeRemainingText = $timeDiffInMinutes > 0
                ? "تبقّى $timeDiffInMinutes دقيقة لبدء الحجز"
                : "الحجز بدأ منذ " . abs($timeDiffInMinutes) . " دقيقة";

            return response()->json([
                'success' => true,
                'message' => 'الطاولة محجوزة حالياً. هذا هو الحجز النشط:',
                'reservation' => [
                    'reservation_id' => $reservation->id,
                    'start_at' => $reservation->start_at->format('Y-m-d H:i'),
                    'end_at' => $reservation->end_at->format('Y-m-d H:i'),
                    'status' => $reservation->status,
                    'number_of_people' => $reservation->number_of_people,
                    'table_id' => $reservation->tableLayout->id,
                    'notes' => $reservation->notes,
                    'time_remaining' => $timeRemainingText,
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'حالة الطاولة غير صالحة.'
        ], 400);
    }
    public function getTodayReservations()
    {
        $today = Carbon::today();

        $reservations = Reservation::with('tableLayout')
        ->whereDate('start_at', $today)
            ->whereIn('status', ['reserved', 'cancelled'])
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json([
                'message' => 'لا يوجد حجوزات محجوزة لليوم.',
                'date' => $today->toDateString(),
                'count' => 0,
                'reservations' => []
            ], 200);
        }
        $formattedReservations = $reservations->map(function ($reservation) {
            return [
                'reservation_id' => $reservation->id,
                'table_id' => $reservation->table_layout_id,
                'number_of_people' => $reservation->number_of_people,
                'notes' => $reservation->notes,
                'from' => Carbon::parse($reservation->start_at)->format('H:i'), // فقط الساعة والدقائق
                'to' => Carbon::parse($reservation->end_at)->format('H:i'), // فقط الساعة والدقائق
                'status' => $reservation->status,
                'cancelled_by' => $reservation->status === 'cancelled' ? $reservation->cancelled_by : null, // من ألغى الحجز
                'cancelled_at' => $reservation->status === 'cancelled' && $reservation->cancelled_at
                    ? Carbon::parse($reservation->cancelled_at)->format('Y-m-d H:i')
                    : null,
                'table_layout' => [
                    'id' => $reservation->tableLayout->id,
                    'number_of_chairs' => $reservation->tableLayout->number_of_chairs,
                    'status' => $reservation->tableLayout->status,
                ]
            ];
        });

        return response()->json([
            'count_of_reservations' => $formattedReservations->count(),
            'date' => $today->toDateString(),
            'reservations' => $formattedReservations
        ]);
    }

}
