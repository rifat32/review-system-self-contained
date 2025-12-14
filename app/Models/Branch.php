<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $hidden = ['pivot'];

    protected $fillable = [
        'business_id',
        'name',
        'address',
        'street',
        'door_no',
        'city',
        'country',
        'postcode',
        'phone',
        'email',
        'is_active',
        'is_geo_enabled',
        'branch_code',
        'lat',
        'long',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
