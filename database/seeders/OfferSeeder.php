<?php

namespace Database\Seeders;

use App\Models\Offer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offers = [
            [
                'title' => 'Weekend Special',
                'description' => '20% off on selected items',
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
                'offer_price' => 20000
            ],
            [
                'title' => 'Happy Hour',
                'description' => '15% off on beverages',
                'starts_at' => now(),
                'ends_at' => now()->addDays(15),
                'offer_price' => 65000
            ],
        ];

        foreach ($offers as $offer) {
            Offer::create($offer);
        }
    }
}
