<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyView extends Model
{
    use HasFactory;
    protected $fillable = [
        "view_date",
        "daily_views",
        "business_id"
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
