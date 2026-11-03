<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // نفرض أن عندك 10 عملاء و5 طاولات موجودة
        $customerIds = DB::table('customers')->pluck('id')->toArray();
        $tableIds = DB::table('table_layouts')->pluck('id')->toArray();

        if (empty($customerIds) || empty($tableIds)) {
            $this->command->info('No customers or tables found. Please seed them first.');
            return;
        }

        $statuses = ['reserved','done','arrived','cancelled'];
        $cancelledBy = ['customer','system', null];

        $reservations = [];

        for ($i = 0; $i < 20; $i++) { // نضيف 20 حجز
            $startAt = Carbon::today()->addHours(rand(8, 20));
            $endAt = (clone $startAt)->addHours(rand(1, 3));

            $status = $statuses[array_rand($statuses)];

            $reservations[] = [
                'customer_id' => $customerIds[array_rand($customerIds)],
                'table_layout_id' => $tableIds[array_rand($tableIds)],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'number_of_people' => rand(1, 8),
                'notes' => Str::random(20),
                'status' => $status,
                'cancelled_by' => $status === 'cancelled' ? $cancelledBy[array_rand($cancelledBy)] : null,
                'cancelled_at' => $status === 'cancelled' ? $startAt->subHours(rand(1, 5)) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('reservations')->insert($reservations);

        $this->command->info('Reservations table seeded successfully!');
    }
}
