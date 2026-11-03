<?php

namespace Database\Seeders;

use App\Models\DeliveryOrder;
use App\Models\OrderItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BigOrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliveryOrder::factory()
            ->count(10000)
            ->create()
            ->each(function ($order) {

                OrderItem::factory()
                    ->count(5)
                    ->create([
                        'delivery_order_id' => $order->id
                    ]);
            });
    }
}
