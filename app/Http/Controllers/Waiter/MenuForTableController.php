<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;

class MenuForTableController extends Controller
{
    public function viewMenuItemsForTable($MenuCategoryId)
    {
        $category = MenuCategory::with('menuItems')->find($MenuCategoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Menu category not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Menu Items by category',
            'category' => [
                'category_id' => $category->id,
                'category_name' => $category->type_name,
                'description' => $category->description,
                'items' => $category->menuItems->map(function ($item) {
                    return [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_image' =>$item->image,
                        'item_price' => $item->price,
                        'item_description' => $item->description,
                        'item_preparation_time' => $item->preparation_time,
                        'is_available' => $item->is_available
                    ];
                })
            ]
        ],200);
    }
}
