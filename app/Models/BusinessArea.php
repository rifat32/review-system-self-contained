<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'business_service_id',
        'area_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the business that owns this area.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * Get the business service that this area belongs to.
     */
    public function businessService(): BelongsTo
    {
        return $this->belongsTo(BusinessService::class, 'business_service_id');
    }

    /**
     * Scope to filter active areas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by business.
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope to filter by business service.
     */
    public function scopeForBusinessService($query, $businessServiceId)
    {
        return $query->where('business_service_id', $businessServiceId);
    }
}
