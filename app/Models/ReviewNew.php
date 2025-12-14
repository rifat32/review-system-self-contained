<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReviewNew extends Model
{
    use HasFactory;
    protected $fillable = [
        'survey_id',
        'description',
        'business_id',
        'rate',
        'user_id',
        'comment',
        'guest_id',
        // "question_id",
        // 'tag_id' ,
        // 'star_id',
        'raw_text',
        'emotion',
        'key_phrases',
        "ip_address",
        "is_overall",
        'staff_id',
        'order_no',
        'sentiment_score',
        'topics',
        'moderation_results',
        'ai_suggestions',
        'staff_suggestions',
        "status",
        'source',
        'language',
        'responded_at',
        'review_type',
        'sentiment',
        'topic_id',
        'reply_content',
        'is_voice_review',
        'voice_url',
        'voice_duration',
        'transcription_metadata',
        'is_private',
        "branch_id",
        "is_ai_processed"

    ];
    protected $casts = [
        'key_phrases' => 'array',
        'topics' => 'array',
        'moderation_results' => 'array',
        'ai_suggestions' => 'array',
        'staff_suggestions' => 'array',
        'is_voice_review' => 'boolean',
        'transcription_metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reviewNew) {
            if (!$reviewNew->order_no) {
                $reviewNew->order_no = static::max('order_no') + 1;
            }
        });
    }

    // public function question() {
    //     return $this->hasOne(Question::class,'id','question_id');
    // }
    // public function tag() {
    //     return $this->hasOne(Question::class,'id','tag_id');
    // }

    public function isVoiceReview()
    {
        return $this->is_voice_review;
    }

    public function getVoiceUrlAttribute($value)
    {
        if (!$value) return null;
        return str_starts_with($value, 'http') ? $value : asset('storage/' . $value);
    }


    public function value()
    {
        return $this->hasMany(ReviewValueNew::class, 'review_id', 'id');
    }

    public function business()
    {
        return $this->hasOne(Business::class, 'id', 'business_id');
    }

    public function staff()
    {
        return $this->hasOne(User::class, 'id', 'staff_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    public function guest_user()
    {
        return $this->hasOne(GuestUser::class, 'id', 'guest_id');
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    // In your QuestionValue model (or the model that has 'value' relation)
    public function scopeFilterByOverall($query, $is_overall)
    {
        return $query->where('is_overall', $is_overall ? 1 : 0);
    }


    public function scopeGlobalFilters($query, $show_published_only = 0, $businessId = null, $is_staff_review = 0)
    {
          
        return $query->when(request()->has('staff_id'), function ($q) {
            $q->where('staff_id', request()->input('staff_id'));
        })
            ->when($show_published_only, function ($q) use ($businessId, $is_staff_review) {
                $q->whereMeetsThreshold($businessId, $is_staff_review);
            });
    }

  public function getReviewByBusinessIdClient($businessId, Request $request)
{
    $query = ReviewNew::with([
        "value",
        "user",
        "guest_user",
        "survey"
    ])->where([
        "business_id" => $businessId,
    ])
    ->when($request->has('is_private'), function ($q) use ($request) {
        $isPrivate = $request->input('is_private');
        if ($isPrivate == 0) {
            // For public reviews, include both is_private = 0 and is_private = null
            $q->where(function ($subQ) {
                $subQ->where('is_private', 0)
                    ->orWhereNull('is_private');
            });
        } else {
            // For private reviews, only is_private = 1
            $q->where('is_private', $isPrivate);
        }
    });

    // Get the IDs first
    $reviewIds = $query->pluck('id')->toArray();
    
    // Calculate ratings for all reviews in bulk
    $ratings = $this->calculateBulkRatings($reviewIds);

    // Sorting logic
    $sortBy = $request->get('sort_by');

    switch ($sortBy) {
        case 'newest':
            $query->orderBy('created_at', 'desc');
            break;
        case 'oldest':
            $query->orderBy('created_at', 'asc');
            break;
        case 'highest_rating':
            // Add calculated rating to results for sorting
            $reviews = $query->get();
            $reviews->each(function ($review) use ($ratings) {
                $review->calculated_rating = $ratings->get($review->id, 0);
            });
            
            // Sort by calculated rating
            $sortedReviews = $reviews->sortByDesc('calculated_rating')->values();
            
            $result = retrieve_data_from_collection($sortedReviews, $request);
            
            return response([
                "success" => true,
                "message" => "Reviews retrieved successfully",
                "meta" => $result['meta'],
                "data" => $result['data']
            ], 200);
        case 'lowest_rating':
            // Add calculated rating to results for sorting
            $reviews = $query->get();
            $reviews->each(function ($review) use ($ratings) {
                $review->calculated_rating = $ratings->get($review->id, 0);
            });
            
            // Sort by calculated rating
            $sortedReviews = $reviews->sortBy('calculated_rating')->values();
            
            $result = retrieve_data_from_collection($sortedReviews, $request);
            
            return response([
                "success" => true,
                "message" => "Reviews retrieved successfully",
                "meta" => $result['meta'],
                "data" => $result['data']
            ], 200);
        default:
            $query->orderBy('created_at', 'desc');
            break;
    }

    $result = retrieve_data($query);
    
    // Add calculated ratings to the response data
    $result['data'] = array_map(function ($review) use ($ratings) {
        $review['calculated_rating'] = $ratings->get($review['id'], 0);
        return $review;
    }, $result['data']);

    return response([
        "success" => true,
        "message" => "Reviews retrieved successfully",
        "meta" => $result['meta'],
        "data" => $result['data']
    ], 200);
}
}
