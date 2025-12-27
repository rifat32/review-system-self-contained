<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'raw_text',
        "ip_address",
        "is_overall",
        'staff_id',
        'order_no',
        "status",
        'source',
        'responded_at',
        'review_type',
        'topic_id',
        'reply_content',
        'is_voice_review',
        'voice_url',
        'voice_duration',
        'transcription_metadata',
        'is_private',
        "branch_id",

        "is_ai_processed",
        'sentiment_score',
        'sentiment',
        'emotion',
        'key_phrases',
        'topics',
        'moderation_results',
        'ai_suggestions',
        'staff_suggestions',
        'language',

        "ai_confidence",
        "sentiment_label",
        'openai_raw_response',
        "is_abusive",
        "summary"

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



    /**
     * Relationship with business_services (many-to-many through pivot)
     */

    public function review_business_services()
    {
        return $this->hasMany(ReviewBusinessService::class, 'review_id', 'id');
    }


    public function business_services(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessService::class,
            'review_business_services', // pivot table
            'review_id',                // foreign key on pivot table
            'business_service_id',       // related key on pivot table
        )
            ->withPivot('business_area_id') // include business_area_id from pivot
            ->withTimestamps();
    }


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
        return $query
        ->when(request()->filled('is_overall'), function ($q) {
                $q->when(request()->boolean('is_overall'), function ($q) {
                    $q->where('review_news.is_overall', 1);
                }, function ($q) {
                    $q->where('review_news.is_overall', 0);
                });
            })
        ->when(request()->has('staff_id'), function ($q) {
            $q->where('review_news.staff_id', request()->input('staff_id'));
        })
            ->when($show_published_only, function ($q) use ($businessId, $is_staff_review) {
                $q->whereMeetsThreshold($businessId, $is_staff_review);
            })


            ->when(request()->filled("question_category_id") || request()->filled("question_sub_category_id"), function ($q) {

                $q
                    ->whereHas("value", function ($q) {
                        $q->whereHas("question", function ($q) {
                            $q->whereHas("question_sub_categories", function ($q) {

                                $q
                                    ->when(request()->filled("question_sub_category_id"), function ($q) {
                                        $q->where("question_categories.id", request()->input("question_sub_category_id"));
                                    })
                                    ->when(request()->filled("question_category_id"), function ($q) {
                                        $q->where("question_categories.parent_id", request()->input("question_category_id"));
                                    });
                            });
                        });
                    });
            })

            ->when(request()->filled("business_area_id") || request()->filled("business_service_id"), function ($q) {

                $q
                    ->whereHas("review_business_services", function ($q) {

                        $q
                            ->when(request()->filled("business_area_id"), function ($q) {
                                $q->where("review_business_services.id", request()->input("business_area_id"));
                            })
                            ->when(request()->filled("business_service_id"), function ($q) {
                                $q->where("review_business_services.business_service_id", request()->input("question_category_id"));
                            });
                    });
            });;


        //  ->when(request()->filled("business_area_id") || request()->filled("business_service_id"), function ($q) {

        //     $q->whereHas("question", function($q){
        //         $q->whereHas("question_sub_categories", function($q){

        //             $q
        //             ->when(request()->filled("question_sub_category_id"), function($q){
        //                 $q->where("question_categories.id", request()->input("question_sub_category_id"));
        //             })
        //             ->when(request()->filled("question_category_id"), function($q){
        //                 $q->where("question_categories.parent_id", request()->input("question_category_id"));
        //             });

        //         });
        //     });
        // });




    }

    /**
     * Add calculated rating to review query
     */
    public function scopeWithCalculatedRating($query)
    {
        return $query->selectRaw('
            review_news.*,
            COALESCE(
                (
                    SELECT ROUND(AVG(DISTINCT s.value), 1)
                    FROM review_value_news rvn
                    INNER JOIN stars s ON rvn.star_id = s.id
                    WHERE rvn.review_id = review_news.id
                ),
                0
            ) as calculated_rating
        ');
    }

    public function scopeWhereMeetsThreshold($query, $businessId, $is_staff_review = 0)
    {
        // Get threshold rating
        $business = Business::find($businessId);
        $thresholdRating = $business->threshold_rating ?? 3; // Default to 3

        return $query->whereExists(function ($subQuery) use ($thresholdRating, $is_staff_review) {
            $subQuery->select(DB::raw(1))
                ->from('review_value_news as rvn')
                ->join('questions as q', 'rvn.question_id', '=', 'q.id')
                ->when((request()->has('staff_id') || $is_staff_review), function ($q) {
                    $q->whereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('q_q_sub_categories as qqsc')
                            ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                            ->join('question_categories as qc_parent', 'qc_sub.parent_id', '=', 'qc_parent.id')
                            ->whereColumn('qqsc.question_id', 'q.id')
                            ->where('qc_parent.title', 'Staff') // Parent category is "Staff"
                            ->where('qc_parent.is_active', 1)
                            ->where('qc_parent.is_default', 1)
                            ->whereNull('qc_parent.business_id')
                            // Also check subcategory if needed
                            ->where('qc_sub.is_active', 1);
                    });
                })
                ->join('stars as s', 'rvn.star_id', '=', 's.id')
                ->whereColumn('rvn.review_id', 'review_news.id')
                ->groupBy('rvn.review_id')
                ->havingRaw('AVG(s.value) >= ?', [$thresholdRating]);
        });
    }


     public function scopeWhereDoesNotMeetsThreshold($query, $businessId, $is_staff_review = 0)
    {
        // Get threshold rating
        $business = Business::find($businessId);
        $thresholdRating = $business->threshold_rating ?? 3; // Default to 3

        return $query->whereExists(function ($subQuery) use ($thresholdRating, $is_staff_review) {
            $subQuery->select(DB::raw(1))
                ->from('review_value_news as rvn')
                ->join('questions as q', 'rvn.question_id', '=', 'q.id')
                ->when((request()->has('staff_id') || $is_staff_review), function ($q) {
                    $q->whereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('q_q_sub_categories as qqsc')
                            ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                            ->join('question_categories as qc_parent', 'qc_sub.parent_id', '=', 'qc_parent.id')
                            ->whereColumn('qqsc.question_id', 'q.id')
                            ->where('qc_parent.title', 'Staff') // Parent category is "Staff"
                            ->where('qc_parent.is_active', 1)
                            ->where('qc_parent.is_default', 1)
                            ->whereNull('qc_parent.business_id')
                            // Also check subcategory if needed
                            ->where('qc_sub.is_active', 1);
                    });
                })
                ->join('stars as s', 'rvn.star_id', '=', 's.id')
                ->whereColumn('rvn.review_id', 'review_news.id')
                ->groupBy('rvn.review_id')
                ->havingRaw('AVG(s.value) < ?', [$thresholdRating]);
        });
    }
}
