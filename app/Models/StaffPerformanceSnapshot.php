<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffPerformanceSnapshot extends Model
{
    use HasFactory;

     protected $fillable = [
        'business_id',
        'staff_id',
        'rating',
        'status',
        'skill_gaps',
        'training_recommendations'
    ];

    protected $casts = [
        'skill_gaps' => 'array',
        'training_recommendations' => 'array'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
    
    // Scope methods
    public function scopeTopPerforming($query)
    {
        return $query->where('status', 'top_performing');
    }
    
    public function scopeNeedsImprovement($query)
    {
        return $query->where('status', 'needs_improvement');
    }







    
}
