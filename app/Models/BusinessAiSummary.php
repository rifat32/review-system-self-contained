<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessAiSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'version',
        'summary',
        'strengths',
        'weaknesses',
        'top_topics',
        'recommendations',
        'trend',
        'confidence',
        'total_reviews',
    ];

    protected $casts = [
        'strengths' => 'array',
        'weaknesses' => 'array',
        'top_topics' => 'array',
        'recommendations' => 'array',
        'confidence' => 'float',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
