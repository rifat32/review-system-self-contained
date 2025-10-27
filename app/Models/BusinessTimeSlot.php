<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessTimeSlot extends Model
{
    use HasFactory;
    protected $fillable = [
        'business_day_id',
        'start_at',
        'end_at',
    ];

    public function businessDay()
    {
        return $this->belongsTo(BusinessDay::class);
    }
}
