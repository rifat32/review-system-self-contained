<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'review_id',
        'external_id',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ReviewNew::class, 'review_id');
    }
}
