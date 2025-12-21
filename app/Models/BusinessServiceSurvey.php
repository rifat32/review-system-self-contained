<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessServiceSurvey extends Model
{
    use HasFactory;

      protected $fillable = [
        'survey_id',
        'business_service_id',
    ];

    /**
     * Get the survey associated with this pivot.
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * Get the business service associated with this pivot.
     */
    public function businessService(): BelongsTo
    {
        return $this->belongsTo(BusinessService::class);
    }


    
}
