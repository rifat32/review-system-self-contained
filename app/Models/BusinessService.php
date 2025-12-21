<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessService extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'is_active',
        'question_title',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the business that owns this service.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }


    public function business_areas()
    {
        return $this->hasMany(BusinessArea::class, 'business_service_id', 'id');
    }

     /**
     * Surveys associated with this business service (NEW RELATIONSHIP)
     */
    public function surveys(): BelongsToMany
    {
        return $this->belongsToMany(
            Survey::class,
            'business_service_surveys',
            'business_service_id',
            'survey_id'
        )->withTimestamps();
    }

    /**
     * Scope to filter active services.
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
}
