<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supply extends Model
{
    protected $fillable = [
        'name',
        'quantity',
        'low_stock',
        'type_of_supply_id'
    ];

    public function usageIngredients()
    {
        return $this->hasMany(UsageIngredient::class);
    }
    public function typeOfSupply()
    {
        return $this->belongsTo(TypeOfSupply::class);
    }
}
