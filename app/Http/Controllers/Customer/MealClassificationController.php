<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MealClassificationController extends Controller
{
    public function getMealsSortedByPrice($categoryId)
    {
        $category = MenuCategory::with(['menuItems' => function ($query) {
            $query->orderBy('price', 'desc')
                ->select('id', 'menu_category_id', 'name', 'price', 'image', 'description', 'preparation_time', 'gifted_loyalty_points', 'meal_price_in_points', 'is_available');
        }])->find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على هذا النوع من الطعام.'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'تم جلب الوجبات مرتبة حسب السعر من الأعلى إلى الأقل.',
            'menu_category' => [
                'menu_category_id' => $category->id,
                'type_name' => $category->type_name,
                'description' => $category->description,
                'items' => $category->menuItems,
            ]
        ]);
    }

    public function getMostOrderedMealsByCategory($categoryId)
    {
        $category = MenuCategory::find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على هذا النوع من الطعام.'
            ], 404);
        }

        $topMeals = DB::table('order_items')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->where('menu_items.menu_category_id', $categoryId)
            ->select('menu_items.id as menu_item_id', DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('menu_items.id')
            ->orderByDesc('total_quantity')
            ->get();

        $menuItems = MenuItem::where('menu_category_id', $categoryId)
            ->get()
            ->map(function ($item) use ($topMeals) {
                $totalOrders = $topMeals->firstWhere('menu_item_id', $item->id)->total_quantity ?? 0;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                    'image' => $item->image,
                    'description' => $item->description,
                    'preparation_time' => $item->preparation_time,
                    'gifted_loyalty_points' => $item->gifted_loyalty_points,
                    'meal_price_in_points' => $item->meal_price_in_points,
                    'is_available' => $item->is_available,
                    'total_orders' => $totalOrders,
                ];
            })
            ->sortByDesc('total_orders')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'الوجبات ضمن النوع مرتبة حسب الأكثر طلباُ.',
            'menu_category' => [
                'menu_category_id' => $category->id,
                'type_name' => $category->type_name,
                'description' => $category->description,
                'items' => $menuItems,
            ]
        ]);
    }

}
