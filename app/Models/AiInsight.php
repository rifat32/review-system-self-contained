<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'type',
        'content',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    
    // Scope methods
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
    
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
