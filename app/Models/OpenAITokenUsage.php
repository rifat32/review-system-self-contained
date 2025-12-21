<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenAITokenUsage extends Model
{
    use HasFactory;

    protected $table = 'openai_token_usage';
    
    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'review_id',
        'branch_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'metadata',
        'created_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'estimated_cost' => 'decimal:6',
        'created_at' => 'datetime'
    ];

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function review()
    {
        return $this->belongsTo(ReviewNew::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Calculate estimated cost based on token usage and model
     */
    public static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Pricing per 1000 tokens (as of 2024)
        $pricing = [
            'gpt-4o-mini' => [
                'input' => 0.00015,  // $0.15 per 1M tokens
                'output' => 0.0006,   // $0.60 per 1M tokens
            ],
            'gpt-4o' => [
                'input' => 0.0025,    // $2.50 per 1M tokens
                'output' => 0.010,     // $10.00 per 1M tokens
            ],
            'gpt-3.5-turbo' => [
                'input' => 0.0005,    // $0.50 per 1M tokens
                'output' => 0.0015,    // $1.50 per 1M tokens
            ],
        ];

        $modelPricing = $pricing[$model] ?? $pricing['gpt-4o-mini'];
        
        $inputCost = ($promptTokens / 1000) * $modelPricing['input'];
        $outputCost = ($completionTokens / 1000) * $modelPricing['output'];
        
        return $inputCost + $outputCost;
    }

    /**
     * Scope queries by date range
     */
    public function scopeDateRange($query, ?string $startDate = null, ?string $endDate = null)
    {
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope queries by business
     */
    public function scopeForBusiness($query, $businessId)
    {
        if ($businessId) {
            $query->where('business_id', $businessId);
        }
        return $query;
    }

    /**
     * Get summary statistics
     */
    public static function getSummary(array $filters = []): array
    {
        $query = self::query();

        // Apply filters
        if (!empty($filters['business_id'])) {
            $query->where('business_id', $filters['business_id']);
        }
        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        if (!empty($filters['model'])) {
            $query->where('model', $filters['model']);
        }

        return $query->selectRaw('
            COUNT(*) as total_requests,
            SUM(prompt_tokens) as total_prompt_tokens,
            SUM(completion_tokens) as total_completion_tokens,
            SUM(total_tokens) as total_tokens,
            SUM(estimated_cost) as total_cost,
            AVG(prompt_tokens) as avg_prompt_tokens,
            AVG(completion_tokens) as avg_completion_tokens,
            AVG(total_tokens) as avg_total_tokens,
            MIN(created_at) as first_request,
            MAX(created_at) as last_request
        ')->first()->toArray();
    }
}