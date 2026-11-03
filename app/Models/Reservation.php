<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'start_at',
        'end_at',
        'status',
        'customer_id',
        'table_layout_id',
        'number_of_people',
        'notes',
        'cancelled_by',
        'cancelled_at'
    ];
    protected $casts = [
        'number_of_people' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime:',
    ];
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tableLayout()
    {
        return $this->belongsTo(TableLayout::class);
    }
}
