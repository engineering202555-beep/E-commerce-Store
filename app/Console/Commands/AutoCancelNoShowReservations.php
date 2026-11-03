<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCancelNoShowReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:auto-cancel-no-shows';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'يلغي الحجوزات التي لم يحضر أصحابها بعد 30 دقيقة من البداية، ويحدّث الحظر.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $this->info(" $now بدء فحص No-Show");

        // حجوزات بدأت من أكثر من 30 دقيقة وما زالت reserved
        $targets = Reservation::with('customer')
            ->where('status', 'reserved')
            ->where('start_at', '<=', $now->copy()->subMinutes(30))
            ->get();

        if ($targets->isEmpty()) {
            $this->info('لا يوجد حجوزات لتحديثها.');
            return Command::SUCCESS;
        }

        foreach ($targets as $res) {
            $res->update([
                'status'       => 'cancelled',
                'cancelled_by' => 'system',
                'cancelled_at' => $now,
            ]);

            // عدّاد المخالفات والحظر
            $customer = $res->customer;
            if ($customer) {
                $customer->no_show_count = ($customer->no_show_count ?? 0) + 1;

                // عند الثالثة: نحظر لشهر ونصفّر العداد (Cycle جديدة)
                if ($customer->no_show_count >= 3) {
                    $customer->banned_until = $now->copy()->addMonth();
                    $customer->no_show_count = 0;
                    $this->warn("حُظر الزبون #{$customer->id} حتى {$customer->banned_until}");
                }

                $customer->save();
            }

            $this->info("تم إلغاء حجز #{$res->id} تلقائيًا (No-Show).");
        }

        return Command::SUCCESS;
    }
}
