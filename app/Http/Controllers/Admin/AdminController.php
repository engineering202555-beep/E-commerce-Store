<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chef;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Supply;
use App\Models\TableLayout;
use App\Models\UsageIngredient;
use App\Models\User;
use App\Models\Waiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function createChef(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:chefs',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'menu_category_id' => 'required|exists:menu_categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'chef'
            ]);

            // Assign chef role
            $user->assignRole('chef');

            // Create chef profile
            Chef::create([
                'user_id' => $user->id,
                'username' => $request->username,
                'menu_category_id' => $request->menu_category_id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Chef account created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create chef account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createWaiter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:waiters',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'waiter'
            ]);

            // Assign waiter role
            $user->assignRole('waiter');

            // Create waiter profile
            Waiter::create([
                'user_id' => $user->id,
                'username' => $request->username
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Waiter account created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create waiter account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllStaff()
    {
        $chefs = Chef::with('user')->get();
        $waiters = Waiter::with('user')->get();

        return response()->json([
            'message' => 'Staff retrieved successfully',
            'data' => [
                'chefs' => $chefs,
                'waiters' => $waiters
            ]
        ]);
    }

    public function updateStaff(User $user, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'username' => 'sometimes|string',
            'menu_category_id' => 'sometimes|exists:menu_categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update user
            if ($request->has('email')) {
                $user->email = $request->email;
            }

            if ($request->has('password')) {
                $user->password = $request->password;
            }

            $user->save();

            // Update profile based on role
            if ($user->role === 'chef') {
                $chef = Chef::where('user_id', $user->id)->first();
                if ($chef) {
                    if ($request->has('username')) {
                        $chef->username = $request->username;
                    }
                    if ($request->has('menu_category_id')) {
                        $chef->menu_category_id = $request->menu_category_id;
                    }
                    $chef->save();
                }
            } elseif ($user->role === 'waiter') {
                $waiter = Waiter::where('user_id', $user->id)->first();
                if ($waiter && $request->has('username')) {
                    $waiter->username = $request->username;
                    $waiter->save();
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Staff updated successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteStaff(User $user)
    {
        DB::beginTransaction();
        try {
            // Delete profile based on role
            if ($user->role === 'chef') {
                Chef::where('user_id', $user->id)->delete();
            } elseif ($user->role === 'waiter') {
                Waiter::where('user_id', $user->id)->delete();
            } elseif ($user->role === 'customer') {
                Customer::where('user_id', $user->id)->delete();
            }

            // Delete user
            $user->delete();

            DB::commit();

            return response()->json([
                'message' => 'Staff deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTable(TableLayout $tableLayout, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number_of_chairs' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:empty,busy,reserved',
            'x_position' => 'sometimes|integer',
            'y_position' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tableLayout->fill($request->only([
                'number_of_chairs',
                'status',
                'x_position',
                'y_position',
            ]));

            $tableLayout->save();

            return response()->json([
                'message' => 'Table updated successfully',
                'data' => $tableLayout
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update table',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addMenuItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'price' => 'required|numeric',
            'description' => 'required|string',
            'preparation_time' => 'required|integer',
            'menu_category_id' => 'required|exists:menu_categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'ingredients' => 'required|array',
            'ingredients.*.supply_id' => 'required|exists:supplies,id',
            'ingredients.*.quantity_required_per_meal' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('menu-items', 'public');
            }

            // Create menu item
            $menuItem = MenuItem::create([
                'name' => $request->name,
                'price' => $request->price,
                'description' => $request->description,
                'preparation_time' => $request->preparation_time,
                'menu_category_id' => $request->menu_category_id,
                'image' => $imagePath,
                'is_available' => true,
                'gifted_loyalty_points' => $request->gifted_loyalty_points ?? 0,
                'meal_price_in_points' => $request->meal_price_in_points ?? 0
            ]);

            // Add ingredients
            foreach ($request->ingredients as $ingredient) {
                UsageIngredient::create([
                    'menu_item_id' => $menuItem->id,
                    'supply_id' => $ingredient['supply_id'],
                    'quantity_required_per_meal' => $ingredient['quantity_required_per_meal']
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Menu item added successfully',
                'data' => $menuItem
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add menu item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMenuItem(MenuItem $menuItem, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'description' => 'sometimes|string',
            'preparation_time' => 'sometimes|integer',
            'menu_category_id' => 'sometimes|exists:menu_categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'sometimes|boolean',
            'ingredients' => 'sometimes|array',
            'ingredients.*.supply_id' => 'required_with:ingredients|exists:supplies,id',
            'ingredients.*.quantity_required_per_meal' => 'required_with:ingredients|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($menuItem->image && file_exists(storage_path('app/public/' . $menuItem->image))) {
                    unlink(storage_path('app/public/' . $menuItem->image));
                }

                // Store new image
                $imagePath = $request->file('image')->store('menu-items', 'public');
                $request->merge(['image' => $imagePath]);
            }

            // Update menu item
            $menuItem->fill($request->only([
                'name',
                'price',
                'description',
                'preparation_time',
                'menu_category_id',
                'image',
                'is_available',
                'gifted_loyalty_points',
                'meal_price_in_points'
            ]));
            $menuItem->save();

            // Update ingredients if provided
            if ($request->has('ingredients')) {
                // Delete existing ingredients
                UsageIngredient::where('menu_item_id', $menuItem->id)->delete();

                // Add new ingredients
                foreach ($request->ingredients as $ingredient) {
                    UsageIngredient::create([
                        'menu_item_id' => $menuItem->id,
                        'supply_id' => $ingredient['supply_id'],
                        'quantity_required_per_meal' => $ingredient['quantity_required_per_meal']
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Menu item updated successfully',
                'data' => $menuItem->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update menu item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteMenuItem(MenuItem $menuItem)
    {
        DB::beginTransaction();
        try {
            // Delete ingredients
            UsageIngredient::where('menu_item_id', $menuItem->id)->delete();

            // Delete menu item
            $menuItem->delete();

            DB::commit();

            return response()->json([
                'message' => 'Menu item deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete menu item',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function addSupply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'quantity' => 'required|numeric',
            'low_stock' => 'sometimes|numeric',
            'type_of_supply_id' => 'required|exists:type_of_supplies,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supply = Supply::create([
                'name' => $request->name,
                'quantity' => $request->quantity,
                'low_stock' => $request->low_stock ?? 0,
                'type_of_supply_id' => $request->type_of_supply_id
            ]);

            return response()->json([
                'message' => 'Supply added successfully',
                'data' => $supply
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add supply',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function updateSupply(Supply $supply, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric',
            'low_stock' => 'sometimes|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supply->quantity = $request->quantity;

            if ($request->has('low_stock')) {
                $supply->low_stock = $request->low_stock;
            }

            $supply->save();

            return response()->json([
                'message' => 'Supply updated successfully',
                'data' => $supply
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update supply',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function deleteSupply(Supply $supply)
    {
        try {
            $supply->delete();

            return response()->json([
                'message' => 'Supply deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete supply',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getAllReports()
    {
        $reports = Report::with(['chef'])->get();

        return response()->json([
            'message' => 'Reports retrieved successfully',
            'data' => $reports
        ]);
    }

    public function viewTables()
    {
        $tables = TableLayout::all();

        return response()->json([
            'message' => 'Tables retrieved successfully',
            'data' => $tables
        ]);
    }

    public function viewReservations()
    {
        $reservations = Reservation::with(['customer', 'tableLayout'])->get();

        return response()->json([
            'message' => 'Reservations retrieved successfully',
            'data' => $reservations
        ]);
    }

    public function viewMenu()
    {
        $menuItems = MenuItem::with(['menuCategory', 'ingredients.supply'])->get();

        return response()->json([
            'message' => 'Menu items retrieved successfully',
            'data' => $menuItems
        ]);
    }

    public function viewSupplies()
    {
        $supplies = Supply::with(['typeOfSupply'])->get();

        return response()->json([
            'message' => 'Supplies retrieved successfully',
            'data' => $supplies
        ]);
    }

    //////Dania:
    public function addTable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number_of_chairs' => 'required|integer|min:1',
            'x_position' => 'required|integer',
            'y_position' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $table = TableLayout::create($request->only([
                'number_of_chairs',
                'status',
                'x_position',
                'y_position',
            ]));
            $tableLayout = new TableLayout();
            $tableLayout->number_of_chairs = $request->number_of_chairs;
            $tableLayout->x_position = $request->x_position;
            $tableLayout->y_position = $request->y_position;
            $tableLayout->status = 'empty';
            $tableLayout->save();

            return response()->json([
                'message' => 'Table created successfully',
                'data' => $tableLayout
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create table',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteTable($tableId)
    {
        try {

            $tableLayout = TableLayout::find($tableId);

            if (!$tableLayout) {
                return response()->json([
                    'message' => 'Table not found'
                ], 404);
            }

            if ($tableLayout->status !== 'empty') {
                return response()->json([
                    'message' => 'Cannot delete table. Wait until it is empty.'
                ], 400);
            }

            $tableLayout->delete();

            return response()->json([
                'message' => 'Table deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete table',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createOffer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date|before_or_equal:ends_at|after:now',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'discounted_price' => 'required|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $discountPercent = $request->discounted_price;
            $items = $request->items;

            $menuItemIds = collect($items)->pluck('menu_item_id')->toArray();
            $menuItems = MenuItem::whereIn('id', $menuItemIds)->get()->keyBy('id');

            $totalOriginalPrice = 0;
            $pivotData = [];

            foreach ($request->items as $item) {
                $menuItemId = $item['menu_item_id'];
                $quantity = $item['quantity'];
                $originalPrice = $menuItems[$menuItemId]->price;

                $totalOriginalPrice += $originalPrice * $quantity;

                $pivotData[$menuItemId] = [
                    'quantity' => $quantity,
                    'discounted_price' => $discountPercent
                ];
            }

            $offerPrice = $totalOriginalPrice - ($totalOriginalPrice * $discountPercent / 100);

            $offer = Offer::create([
                'title' => $request->title,
                'description' => $request->description,
                'offer_price' => $offerPrice,
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at,
            ]);

            $offer->menuItems()->attach($pivotData);
            $offer->load('menuItems');


            DB::commit();

            $menuItemsResponse = $offer->menuItems()->get()->map(function ($item) {
                $quantity = $item->pivot->quantity;
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                    'quantity' => $quantity,
                    'image_url' => $item->image_url,
                ];
            });

            $response = [
                'title' => $offer->title,
                'description' => $offer->description,
                'discount_percent' => $discountPercent,
                'total_before_discount' => $totalOriginalPrice,
                'total_after_price' => $offerPrice,
                'starts_at' => $offer->starts_at,
                'ends_at' => $offer->ends_at,
                'id' => $offer->id,
                'menu_items' => $menuItemsResponse
            ];

            return response()->json([
                'message' => 'Offer created successfully',
                'data' => $response
            ], 201);


        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateOffer(Request $request, $offerId)
    {
        $offer = Offer::find($offerId);

        if (!$offer) {
            return response()->json([
                'message' => 'Offer not found'
            ], 404);
        }

        if ($offer->ends_at < now()) {
            return response()->json([
                'message' => 'Cannot update this offer. Either it does not exist or has already ended.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'sometimes|date|before_or_equal:ends_at|after:now',
            'ends_at' => 'sometimes|date|after_or_equal:starts_at',
            'discounted_price' => 'sometimes|numeric|min:0|max:100',
            'items' => 'sometimes|array|min:1',
            'items.*.menu_item_id' => 'required_with:items|exists:menu_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $offer->fill($request->only(['title', 'description', 'starts_at', 'ends_at']));
            $offer->save();

            $discountPercent = $request->filled('discounted_price') ? (float)$request->discounted_price
                : (float)$offer->menuItems()->first()->pivot->discounted_price;;

            // تحديث الوجبات إذا تم ارسالها
            if ($request->has('items')) {
                $items = $request->items;
                $menuItemIds = collect($items)->pluck('menu_item_id')->toArray();
                $menuItems = MenuItem::whereIn('id', $menuItemIds)->get()->keyBy('id');

                $totalBeforeDiscount = 0;
                $pivotData = [];

                foreach ($items as $item) {
                    $menuItemId = $item['menu_item_id'];
                    $quantity = $item['quantity'];
                    $originalPrice = $menuItems[$menuItemId]->price;

                    $totalBeforeDiscount += $originalPrice * $quantity;

                    $pivotData[$menuItemId] = [
                        'quantity' => $quantity,
                        // إذا تم تعديل نسبة الحسم نستخدمها، وإلا نحتفظ بالقيمة القديمة
                        'discounted_price' => $discountPercent ?? $offer->menuItems()->where('menu_item_id', $menuItemId)->first()->pivot->discounted_price ?? 0
                    ];
                }

                $offer->menuItems()->updateExistingPivot($menuItemId, [
                    'quantity' => $quantity,
                    'discounted_price' => $discountPercent
                ]);


                // إعادة حساب السعر الإجمالي بعد الحسم
                if ($discountPercent !== null) {
                    $offerPrice = round($totalBeforeDiscount * (1 - $discountPercent / 100), 2);
                    $offer->offer_price = $offerPrice;
                } else {
                    // إذا لم يتم تعديل الخصم، حسب السعر الجديد بعد الحسم بناءً على النسبة السابقة
                    $pivotDiscounts = $offer->menuItems->pluck('pivot.discounted_price', 'id')->toArray();
                    $offerPrice = 0;
                    foreach ($offer->menuItems as $item) {
                        $quantity = $item->pivot->quantity;
                        $discount = $pivotDiscounts[$item->id];
                        $offerPrice += $item->price * $quantity * (1 - $discount / 100);
                    }
                    $offer->offer_price = round($offerPrice, 2);
                }

                $offer->save();
            } else if ($discountPercent !== null) {
                // إذا تم تعديل نسبة الحسم فقط بدون تغيير الوجبات
                $totalBeforeDiscount = 0;
                foreach ($offer->menuItems as $item) {
                    $quantity = $item->pivot->quantity;
                    $totalBeforeDiscount += $item->price * $quantity;
                    // تحديث نسبة الحسم لكل وجبة
                    $item->pivot->discounted_price = $discountPercent;
                    $item->pivot->save();
                }
                $offer->offer_price = round($totalBeforeDiscount * (1 - $discountPercent / 100), 2);
                $offer->save();
            }

            $offer->load('menuItems');

            // تجهيز الـ JSON response
            $menuItemsResponse = $offer->menuItems->map(function ($item) {
                $quantity = $item->pivot->quantity;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                    'quantity' => $quantity,
                    'image_url' => $item->image_url,
                ];
            });

            $totalBeforeDiscount = 0;
            foreach ($offer->menuItems as $item) {
                $totalBeforeDiscount += $item->price * $item->pivot->quantity;
            }

            $response = [
                'title' => $offer->title,
                'description' => $offer->description,
                'discount_percent' => $discountPercent ?? $offer->menuItems->first()->pivot->discounted_price,
                'total_before_discount' => $totalBeforeDiscount,
                'total_after_discount' => $offer->offer_price,
                'starts_at' => $offer->starts_at,
                'ends_at' => $offer->ends_at,
                'id' => $offer->id,
                'menu_items' => $menuItemsResponse
            ];

            DB::commit();

            return response()->json([
                'message' => 'Offer updated successfully',
                'data' => $response
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOffers()
    {
        try {

            $offers = Offer::where('ends_at', '>=', now())->with('menuItems')->get();

            $offersResponse = $offers->map(function ($offer) {
                $totalBeforeDiscount = 0;

                $menuItems = $offer->menuItems->map(function ($item) use (&$totalBeforeDiscount) {
                    $quantity = $item->pivot->quantity;
                    $priceBefore = $item->price * $quantity;
                    $totalBeforeDiscount += $priceBefore;

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price,
                        'quantity' => $quantity,
                        'image_url' => $item->image_url,
                    ];
                });

                $discountPercent = $offer->menuItems->first() ? $offer->menuItems->first()->pivot->discounted_price : 0;

                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'discount_percent' => $discountPercent,
                    'total_before_discount' => $totalBeforeDiscount,
                    'total_after_discount' => $offer->offer_price,
                    'starts_at' => $offer->starts_at,
                    'ends_at' => $offer->ends_at,
                    'menu_items' => $menuItems,
                ];
            });

            return response()->json([
                'message' => 'Active offers retrieved successfully',
                'data' => $offersResponse
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve offers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteOffer($offerId)
    {
        try {
            $offer = Offer::find($offerId);

            if (!$offer) {
                return response()->json([
                    'message' => 'Offer not found'
                ], 404);
            }

            // حذف السجلات المرتبطة بالجدول الوسيط
            $offer->menuItems()->detach();

            // حذف العرض نفسه
            $offer->delete();

            return response()->json([
                'message' => 'Offer deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }




}
