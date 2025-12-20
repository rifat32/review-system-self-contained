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
        'manager_id',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function scopeFilters($query)
    {
        // Apply search filter
        if ($request->has('search_key') && !empty(request()->search_key)) {
            $searchKey = request()->search_key;
            $query->where(function ($q) use ($searchKey) {
                $q->where('name', 'like', '%' . $searchKey . '%')
                    ->orWhere('branch_code', 'like', '%' . $searchKey . '%');
            });
        }

        return $query;
    }
}
