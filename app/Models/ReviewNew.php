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
                $q->whereMeetsThreshold($businessId, 1, $is_staff_review);
            });
    }

    public function scopeWhereMeetsThreshold($query, $businessId, $is_staff_review = 0)
    {

        // Get threshold rating
        $business = \App\Models\Business::find($businessId);
        $thresholdRating = $business->threshold_rating ?? 3; // Default to 3

        return $query->whereExists(function ($subQuery) use ($thresholdRating, $is_staff_review) {
            $subQuery->select(DB::raw(1))
                ->from('review_value_news as rvn')
                ->join('questions as q', 'rvn.question_id', '=', 'q.id')
                ->when((request()->has('staff_id') || $is_staff_review), function ($q) {
                    $q->where('q.is_staff', 1);
                })
                ->join('stars as s', 'rvn.star_id', '=', 's.id')
                ->whereColumn('rvn.review_id', 'review_news.id')
                ->groupBy('rvn.review_id')
                ->havingRaw('AVG(s.value) >= ?', [$thresholdRating]);
        });
    }
}
