<?php

namespace Database\Seeders;

use App\Models\Report;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reports = [
            [
                'chef_id' => 1,
                'waiter_id' => null,
                'title' => 'Low Tomato Supply',
                'body' => 'We are running low on tomatoes. Please restock.',
            ],
            [
                'chef_id' => 2,
                'waiter_id' => null,
                'title' => 'Equipment Issue',
                'body' => 'The oven in station 2 is not working properly.',
            ],
        ];

        foreach ($reports as $report) {
            Report::create($report);
        }
    }
}
