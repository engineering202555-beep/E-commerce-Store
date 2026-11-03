<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;

class TypesAndDetailsOfMeals extends Controller
{
    public function getAllFoodCategories()
    {
        $categories = MenuCategory::select('id', 'type_name','description')->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'success' => true,
                'categories' => [],
                'message' => 'لا توجد أنواع طعام متوفرة حالياً.'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => ['أنواع الطعام كالتالي'],
            'categories' => $categories
        ], 200);
    }

    public function getMealsByCategory($categoryId)
    {
        $category = MenuCategory::with(['menuItems' => function($query) {
            $query->select('id', 'menu_category_id','name', 'price',
                'image', 'description', 'preparation_time', 'gifted_loyalty_points',
                'meal_price_in_points','is_available');
        }])->find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على هذا النوع من الطعام.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'وجبات هذا النوع من الطعام.',
            'menu_category' => [
                'menu_category_id' => $category->id,
                'type_name' => $category->type_name,
                'description' => $category->description,
                'items' => $category->menuItems,
            ]
        ]);
    }

    public function getMealDetails($categoryId, $mealId)
    {
        $category = MenuCategory::find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على هذا النوع من الطعام.'
            ], 404);
        }

        $meal = $category->menuItems()
            ->where('id', $mealId)
            ->select('id', 'menu_category_id', 'name', 'price', 'image', 'description', 'preparation_time', 'gifted_loyalty_points', 'meal_price_in_points', 'is_available')
            ->first();

        if (!$meal) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الوجبة غير موجودة ضمن هذا النوع.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تفاصيل الوجبة.',
            'meal' => $meal
        ]);
    }



}
