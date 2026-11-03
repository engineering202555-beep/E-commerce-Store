<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuCategoryController extends Controller
{
    // Get all categories
    public function index()
    {
        $categories = MenuCategory::all();
        return response()->json($categories);
    }

    // Create category
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = MenuCategory::create($request->only(['type_name', 'description']));

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category,
        ]);
    }

    // Show single category
    public function show(MenuCategory $menuCategory)
    {
        return response()->json($menuCategory);
    }

    // Update category
    public function update(Request $request, MenuCategory $menuCategory)
    {
        $validator = Validator::make($request->all(), [
            'type_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $menuCategory->update($request->only(['type_name', 'description']));

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $menuCategory,
        ]);
    }

    // Delete category
    public function destroy(MenuCategory $menuCategory)
    {
        $menuCategory->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
