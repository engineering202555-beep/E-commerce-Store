<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MenuCategory::insert([
            ['type_name' => 'مشروبات باردة', 'description' => 'يتضمن مشرويات غازية ومياه وغيرها'],
            ['type_name' => 'مشروبات ساخنة', 'description' => 'مشروبات ساخنة فقط'],
            ['type_name' => 'اكل شرقي', 'description' => 'كل المأكولات الشرقية غير متضمنة السلطات'],
            ['type_name' => 'اكل غربي', 'description' => 'السندويشات الغربية أو الوجبات الغربية'],
            ['type_name' => 'اراكيل', 'description' => 'أنواع محتلفة من المعسل والنكهات'],
            ['type_name' => 'حلويات', 'description' => 'تشكيلة من الحلويات غير العربية'],
            ['type_name' => 'معجنات', 'description' => 'معجنات وأطباق مخبوزة بعناية'],
            ['type_name' => 'سلطات', 'description' => 'سلطات مختلفة'],
        ]);
    }
}
