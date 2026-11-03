<?php

namespace Database\Seeders;

use App\Models\UsageIngredient;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsageIngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UsageIngredient::insert([
            ['menu_item_id' => 1, 'supply_id' => 6, 'quantity_required_per_meal' => 0.1],
            ['menu_item_id' => 2, 'supply_id' => 8, 'quantity_required_per_meal' => 0.05],
            ['menu_item_id' => 3, 'supply_id' => 9, 'quantity_required_per_meal' => 0.5],
            ['menu_item_id' => 3, 'supply_id' => 10, 'quantity_required_per_meal' => 0.5],
            ['menu_item_id' => 3, 'supply_id' => 3, 'quantity_required_per_meal' => 0.005],
            ['menu_item_id' => 4, 'supply_id' => 10, 'quantity_required_per_meal' => 0.6],
            ['menu_item_id' => 4, 'supply_id' => 3, 'quantity_required_per_meal' => 0.005],
            ['menu_item_id' => 5, 'supply_id' => 11, 'quantity_required_per_meal' => 0.1],
            ['menu_item_id' => 6, 'supply_id' => 12, 'quantity_required_per_meal' => 0.05],
            ['menu_item_id' => 6, 'supply_id' => 5, 'quantity_required_per_meal' => 0.1],
            ['menu_item_id' => 7, 'supply_id' => 12, 'quantity_required_per_meal' => 0.25],
            ['menu_item_id' => 7, 'supply_id' => 1, 'quantity_required_per_meal' => 0.5],
            ['menu_item_id' => 7, 'supply_id' => 4, 'quantity_required_per_meal' => 0.25],
            ['menu_item_id' => 7, 'supply_id' => 3, 'quantity_required_per_meal' => 0.005],
            ['menu_item_id' => 8, 'supply_id' => 1, 'quantity_required_per_meal' => 0.25],
            ['menu_item_id' => 8, 'supply_id' => 2, 'quantity_required_per_meal' => 1],
            ['menu_item_id' => 8, 'supply_id' => 3, 'quantity_required_per_meal' => 0.005],
        ]);
    }
}
