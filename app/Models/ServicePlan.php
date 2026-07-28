<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePlan extends Model
{
    use HasFactory;

    protected $appends = [
        'estimated_tokens_per_review',
        'estimated_reviews_limit'
    ];

    protected $fillable = [
        "name",
        "description",
        "set_up_amount",
        'price',
        'duration_months',
        'openai_token_limit',
        'free_trial_duration_date',
        'is_active',
        "created_by"
    ];

    public function service_plan_modules()
    {
        return $this->hasMany(ServicePlanModule::class, 'service_plan_id', 'id');
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'service_plan_modules', 'service_plan_id', 'module_id');
    }

    public function getEstimatedTokensPerReviewAttribute(): int
    {
        // Eager load if possible, or pluck directly
        $activeModules = $this->modules->pluck('name')->toArray();
        return \App\Services\AIProcessor\OpenAIProcessorService::estimateTokensPerReview($activeModules);
    }

    public function getEstimatedReviewsLimitAttribute(): int
    {
        $limit = $this->openai_token_limit;
        if ($limit === -1) {
            return -1;
        }
        if ($limit === 0 || $limit === null) {
            return 0;
        }
        $estPerReview = $this->estimated_tokens_per_review;
        return (int)floor($limit / $estPerReview);
    }
}
