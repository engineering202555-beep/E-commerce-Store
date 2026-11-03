<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuItem::query()
            ->with('menuCategory');

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($request->filled('category_id')) {
            $query->where(
                'menu_category_id',
                $request->integer('category_id')
            );
        }

        if ($request->filled('min_price')) {
            $query->where(
                'price',
                '>=',
                $request->input('min_price')
            );
        }

        if ($request->filled('max_price')) {
            $query->where(
                'price',
                '<=',
                $request->input('max_price')
            );
        }

        if ($request->filled('is_available')) {
            $query->where(
                'is_available',
                $request->boolean('is_available')
            );
        }

        $products = $query
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products,
        ], 200);
    }

    public function show(MenuItem $product)
    {
        $product->load('menuCategory');

        return response()->json([
            'success' => true,
            'data' => $product,
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'preparation_time' => ['nullable', 'integer', 'min:0'],
            'menu_category_id' => [
                'required',
                'integer',
                'exists:menu_categories,id',
            ],
            'gifted_loyalty_points'=>['nullable','numeric','min:1'],

              'meal_price_in_points'=>['nullable','numeric','min:3'],

            'is_available' => ['required', 'boolean'],
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request
                ->file('image')
                ->store('products', 'public');
        }

        $product = MenuItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('menuCategory'),
        ], 201);
    }

    public function update(Request $request, MenuItem $product)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string'],
            'preparation_time' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],
            'menu_category_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:menu_categories,id',
            ],
            'is_available' => ['sometimes', 'boolean'],
            'image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request
                ->file('image')
                ->store('products', 'public');
                if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
        }

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->fresh()->load('menuCategory'),
        ], 200);
    }

    public function destroy(MenuItem $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ], 200);
    }
}