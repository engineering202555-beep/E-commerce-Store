<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'is_available',
        'image',
        'price',
        'description',
        'preparation_time',
        'gifted_loyalty_points',
        'meal_price_in_points',
        'menu_category_id'
    ];
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url('storage/' . $this->image);
        }
        return null;
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function menuCategory()
    {
        return $this->belongsTo(MenuCategory::class);
    }
    public function usageIngredients()
    {
        return $this->hasMany(UsageIngredient::class);
    }
    public function offers() {
        return $this->belongsToMany(Offer::class, 'menu_item_offer_pivots');
    }
    public function favouriteByCustomers()
    {
        return $this->belongsToMany(Customer::class);
    }

}
