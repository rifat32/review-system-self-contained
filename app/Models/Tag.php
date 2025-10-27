<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;
    protected $fillable = [
        'tag',
        "business_id",
        "is_default",
        "is_active"
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        "business_id",
    ];
}
