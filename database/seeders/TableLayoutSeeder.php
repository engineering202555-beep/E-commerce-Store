<?php

namespace Database\Seeders;

use App\Models\TableLayout;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TableLayoutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TableLayout::insert([
            ['number_of_chairs' => 4, 'status' => 'empty','x_position' => -420, 'y_position' => 260],
            ['number_of_chairs' => 2, 'status' => 'empty','x_position' => -170, 'y_position' => 170],
            ['number_of_chairs' => 6, 'status' => 'empty','x_position' => -170, 'y_position' => 330],
            ['number_of_chairs' => 8, 'status' => 'empty','x_position' => 90, 'y_position' => 250],
            ['number_of_chairs' => 4, 'status' => 'empty','x_position' => 360, 'y_position' => 170],
            ['number_of_chairs' => 4, 'status' => 'empty','x_position' => 360, 'y_position' => 330],
            ['number_of_chairs' => 4, 'status' => 'empty','x_position' => 590, 'y_position' => 260],

        ]);
    }
}
