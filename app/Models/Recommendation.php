<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'insight_id',
        'rule_id',
        'type',
        'text',
        'confidence',
        'priority',
        'evidence',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => 'integer',
        'evidence' => 'array',
        'insight_id' => 'integer',
        'rule_id' => 'integer',
        'business_id' => 'integer',
    ];


    public function insight()
    {
        return $this->belongsTo(InsightRecord::class, 'insight_id');
    }

    public function rule()
    {
        return $this->belongsTo(AiRule::class, 'rule_id');
    }
}
