<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\TableLayout;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateTableStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tables:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تحديث حالة الطاولات حسب الحجوزات (reserved / empty)';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $tables = TableLayout::all();

        foreach ($tables as $table) {
            $this->info("➡ Checking table {$table->id}, status={$table->status}");

            $oldStatus = $table->status;
            $newStatus = $oldStatus;

            $reservations = Reservation::where('table_layout_id', $table->id)
                ->where('status', 'reserved')
                ->orderBy('start_at')
                ->get();

            $hasActiveReservation = false;

            foreach ($reservations as $reservation) {
                $start = Carbon::parse($reservation->start_at)->subMinutes(10);
                $end = Carbon::parse($reservation->end_at)->addMinutes(10);

                $this->info("   Reservation: {$reservation->start_at} → {$reservation->end_at}");
                $this->info("   Active window: {$start} → {$end}");

                if ($now->between($start, $end)) {
                    $hasActiveReservation = true;
                }
            }

            // الآن خارج اللوب نقرر الحالة
            if ($hasActiveReservation) {
                if ($oldStatus !== 'reserved') {
                    $newStatus = 'reserved';
                }
            } else {
                if ($oldStatus === 'reserved') {
                    $newStatus = 'empty';
                }
            }

            if ($oldStatus !== $newStatus) {
                $table->update(['status' => $newStatus]);
                $this->info("تم تحديث الطاولة {$table->id} من {$oldStatus} إلى {$newStatus}");
            } else {
                $this->line("↔ Table {$table->id} remains {$oldStatus}");
            }
        }

    }

}
/////php artisan tables:update-statuses
/////php artisan schedule:work
