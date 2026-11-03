<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MenuItem::insert([
            [
                'menu_category_id' => 1,
                'name' => 'عصير برتقال',
                'is_available' => true,
                'image' => 'orange.jpg',
                'price' => 20000,
                'description' => 'عصير برتقال طبيعي طازج',
                'preparation_time' => 5,
                'gifted_loyalty_points' => 2,
                'meal_price_in_points' => 4
            ],
            [
                'menu_category_id' => 2,
                'name' => 'قهوة تركية',
                'is_available' => true,
                'image' => 'turkish_coffee.jpg',
                'price' => 10500,
                'description' => 'قهوة تركية على الفحم',
                'preparation_time' => 7,
                'gifted_loyalty_points' => 1,
                'meal_price_in_points' => 2
            ],
            [
                'menu_category_id' => 3,
                'name' => 'مشاوي مشكلة',
                'is_available' => true,
                'image' => 'mixed_grill.jpg',
                'price' => 15000,
                'description' => 'طبق مشاوي شرقية متنوعة',
                'preparation_time' => 20,
                'gifted_loyalty_points' => 50,
                'meal_price_in_points' => 60
            ],
            [
            'menu_category_id' => 4,
            'name' => 'غوردون بلو',
            'is_available' => true,
            'image' => 'mixed_grill.jpg',
            'price' => 60800,
            'description' => 'طبق غربي مزيج بين الدجاج وكريمة البشاميل',
            'preparation_time' => 20,
            'gifted_loyalty_points' => 10,
            'meal_price_in_points' => 15
             ],
             [
                'menu_category_id' => 5,
                'name' => 'اركيلة تفاحتين',
                'is_available' => true,
                'image' => 'mixed_grill.jpg',
                'price' => 40000,
                'description' => 'معسل تفاحتين نخلة',
                'preparation_time' => 5,
                'gifted_loyalty_points' => 0,
                'meal_price_in_points' => 0
            ],
            [
                'menu_category_id' => 6,
                'name' => 'كريب',
                'is_available' => true,
                'image' => 'mixed_grill.jpg',
                'price' => 35000,
                'description' => 'كريب بالفواكه ومع الشوكولا',
                'preparation_time' => 5,
                'gifted_loyalty_points' => 6,
                'meal_price_in_points' => 4
            ],
            [
                'menu_category_id' => 7,
                'name' => 'بيتزا',
                'is_available' => true,
                'image' => 'mixed_grill.jpg',
                'price' => 45000,
                'description' => 'بيتزا الفصول الأربعة',
                'preparation_time' => 10,
                'gifted_loyalty_points' => 25,
                'meal_price_in_points' => 18
            ],
            [
                'menu_category_id' => 8,
                'name' => 'تبولة',
                'is_available' => true,
                'image' => 'mixed_grill.jpg',
                'price' => 14000,
                'description' => 'سلطة من البقدونس الطازج',
                'preparation_time' => 5,
                'gifted_loyalty_points' => 3,
                'meal_price_in_points' => 2
            ],

        ]);

    }
}
