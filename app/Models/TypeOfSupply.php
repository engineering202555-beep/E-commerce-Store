<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeOfSupply extends Model
{
    protected $fillable = [
        'name_of_supplies'
    ];

    public function supplies()
    {
        return $this->hasMany(Supply::class);
    }
}
