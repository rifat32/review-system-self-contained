<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleBusinessReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_business_location_id',
        'review_id',
        'reviewer_name',
        'reviewer_photo_url',
        'star_rating',
        'comment',
        'review_reply',
        'review_reply_updated_at',
        'review_created_at',
        'review_updated_at',
    ];

    protected $casts = [
        'review_reply_updated_at' => 'datetime',
        'review_created_at' => 'datetime',
        'review_updated_at' => 'datetime',
    ];

    /**
     * Get the location that owns this review
     */
    public function location()
    {
        return $this->belongsTo(GoogleBusinessLocation::class, 'google_business_location_id');
    }

    /**
     * Get numeric star rating (1-5)
     */
    public function getNumericRatingAttribute(): int
    {
        $ratings = [
            'ONE' => 1,
            'TWO' => 2,
            'THREE' => 3,
            'FOUR' => 4,
            'FIVE' => 5,
        ];

        return $ratings[$this->star_rating] ?? 0;
    }

    /**
     * Check if review has a reply
     */
    public function hasReply(): bool
    {
        return !empty($this->review_reply);
    }

    /**
     * Scope to filter by star rating
     */
    public function scopeByRating($query, $rating)
    {
        $ratingMap = [
            1 => 'ONE',
            2 => 'TWO',
            3 => 'THREE',
            4 => 'FOUR',
            5 => 'FIVE',
        ];

        if (isset($ratingMap[$rating])) {
            return $query->where('star_rating', $ratingMap[$rating]);
        }

        return $query;
    }

    /**
     * Scope to get recent reviews
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('review_created_at', '>=', now()->subDays($days));
    }
}
