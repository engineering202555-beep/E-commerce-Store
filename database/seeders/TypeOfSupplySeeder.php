<?php

namespace Database\Seeders;

use App\Models\TypeOfSupply;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TypeOfSupplySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TypeOfSupply::insert([
            ['name_of_supplies' => 'عتاد'],
            ['name_of_supplies' => 'مكونات لتحضير الوجبة'],
            ['name_of_supplies' => 'بهارات'],
            ['name_of_supplies' => 'خضار'],
            ['name_of_supplies' => 'فواكه'],
            ['name_of_supplies' => 'معسل'],
        ]);
    }
}
