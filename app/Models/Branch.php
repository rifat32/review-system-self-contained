<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'address',
        'phone',
        'email',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
