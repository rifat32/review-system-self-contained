<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleBusinessLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_business_account_id',
        'location_id',
        'location_name',
        'address',
        'phone',
        'website',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the account that owns this location
     */
    public function account()
    {
        return $this->belongsTo(GoogleBusinessAccount::class, 'google_business_account_id');
    }

    /**
     * Get all reviews for this location
     */
    public function reviews()
    {
        return $this->hasMany(GoogleBusinessReview::class);
    }

    /**
     * Scope to get only active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Mark this location as synced
     */
    public function markAsSynced()
    {
        $this->update(['last_synced_at' => now()]);
    }
}
