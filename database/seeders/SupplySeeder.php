<?php

namespace Database\Seeders;

use App\Models\Supply;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Supply::insert([
            ['type_of_supply_id' => 4, 'name' => 'طماطم', 'quantity' => 24, 'low_stock' => 3],
            ['type_of_supply_id' => 4, 'name' => 'بقدونس', 'quantity' => 20, 'low_stock' => 2],
            ['type_of_supply_id' => 3, 'name' => 'ملح', 'quantity' => 10, 'low_stock' => 1],
            ['type_of_supply_id' => 2, 'name' => 'جبنة موزاريلا', 'quantity' => 50, 'low_stock' => 5],
            ['type_of_supply_id' => 5, 'name' => 'فريز', 'quantity' => 8, 'low_stock' => 2],
            ['type_of_supply_id' => 5, 'name' => 'برتقال', 'quantity' => 8, 'low_stock' => 2],
            ['type_of_supply_id' => 1, 'name' => 'كرسي', 'quantity' => 70, 'low_stock' => 0],
            ['type_of_supply_id' => 2, 'name' => 'بن', 'quantity' => 45, 'low_stock' => 2],
            ['type_of_supply_id' => 2, 'name' => 'لحمة', 'quantity' => 40, 'low_stock' => 5],
            ['type_of_supply_id' => 2, 'name' => 'دجاج', 'quantity' => 40, 'low_stock' => 5],
            ['type_of_supply_id' => 6, 'name' => 'معسل', 'quantity' => 45, 'low_stock' => 3],
            ['type_of_supply_id' => 2, 'name' => 'طحين', 'quantity' => 100, 'low_stock' => 4],
        ]);
    }
}
