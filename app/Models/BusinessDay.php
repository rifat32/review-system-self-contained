<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessDay extends Model
{
    protected $fillable = [
        'day',
        'business_id',
        'is_weekend'
    ];

    public function timeSlots()
    {
        return $this->hasMany(BusinessTimeSlot::class);
    }

}
