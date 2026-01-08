<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsightRecord extends Model
{
    
     /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'main_category',
        'sub_category',
        'mentions_count',
        'severity',
        'confidence_level',
        'trend',
        'staff_blame_detected',
        'review_ids',
        'time_window_start',
        'time_window_end',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'staff_blame_detected' => 'boolean',
        'review_ids' => 'array',
        'time_window_start' => 'date',
        'time_window_end' => 'date',
    ];
    public function recommendations()
{
    return $this->hasMany(Recommendation::class, 'insight_id');
}

}
