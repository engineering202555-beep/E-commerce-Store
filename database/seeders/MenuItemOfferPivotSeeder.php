<?php

namespace Database\Seeders;

use App\Models\MenuItemOfferPivot;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuItemOfferPivotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
        public function run(): void
    {
        $pivots = [
            [
                'menu_item_id' => 1,   // ID من جدول menu_items
                'offer_id' => 1,       // ID من جدول offers
                'discounted_price' => 18000,  // السعر بعد الخصم
                'quantity' => 2
            ],
            [
                'menu_item_id' => 2,
                'offer_id' => 1,
                'discounted_price' => 15000,
                'quantity' => 1
            ],
            [
                'menu_item_id' => 3,
                'offer_id' => 2,
                'discounted_price' => 55000,
                'quantity' => 3
            ]
        ];

        foreach ($pivots as $pivot) {
            MenuItemOfferPivot::create($pivot);
        }

    }
}
