<?php

namespace App\Models;

use App\Services\Rule\RuleEngineService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReviewNew extends Model
{
    use HasFactory;

    const PLATFORM_WEB = 'web';
    const PLATFORM_APP = 'app';

    protected $fillable = [

        'survey_id',
        'description',
        'business_id',
        'user_id',
        'comment',
        'guest_id',
        'raw_text',
        "ip_address",
        "is_overall",
        'staff_id',
        'order_no',
        "status",
        'responded_at',
        'review_type',
        'reply_content',
        'is_voice_review',
        'voice_url',
        'voice_duration',
        'transcription_metadata',
        'is_private',
        "branch_id",

        "is_ai_processed",
        'sentiment_score',
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
        "summary",
        'source',
        "rating_comment_mismatch",
        "mismatch_insights",
        "ai_insights",
        "ai_recommendations",
        "audio",
        "service_analysis"
    ];



    protected $casts = [
        "emotion" => "array",
        'key_phrases' => 'array',
        'topics' => 'array',
        'moderation_results' => 'array',
        'ai_suggestions' => 'array',
        'staff_suggestions' => 'array',
        'is_voice_review' => 'boolean',
        'transcription_metadata' => 'array',
        'sentiment_score' => 'float',
        'is_flagged' => 'boolean',
        'ai_insights' => 'array',
        'ai_recommendations' => 'array',
        'topics' => 'array',
        'service_analysis' => 'array',
        'openai_raw_response' => 'array',
        'mismatch_insights' => 'array',
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

    public function getSentimentLabelAttribute($value)
    {
        $sentimentScore = $this->attributes['sentiment_score'] ?? null;

        if ($sentimentScore === null) {
            return 'neutral';
        }

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        if ($sentimentScore >= $positiveThreshold) {
            return 'positive';
        } elseif ($sentimentScore < $negativeThreshold) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }

    public function getAiMetadataAttribute()
    {
        if (empty($this->openai_raw_response)) return [];
        return is_array($this->openai_raw_response)
            ? $this->openai_raw_response
            : json_decode($this->openai_raw_response, true);
    }

    public function setSourceAttribute($value)
    {
        if (!in_array($value, [self::PLATFORM_WEB, self::PLATFORM_APP])) {
            throw new \InvalidArgumentException("Invalid platform value: $value. Allowed values are: " . self::PLATFORM_WEB . ", " . self::PLATFORM_APP);
        }
        $this->attributes['source'] = $value;
    }



    public function getVoiceUrlAttribute($value)
    {
        if (!$value)
            return null;
        return str_starts_with($value, 'http') ? $value : asset('storage/' . $value);
    }

    public function getAudioAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        return asset("storage-proxy/business_{$this->business->OwnerID}/business_{$this->business_id}/voice-reviews/{$value}");
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

    // ReviewNews.php (Model)

    public function review_values()
    {
        return $this->hasMany(ReviewValueNew::class, 'review_id');
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



    public function getCalculatedRatingAttribute()
    {
        return round(
            $this->review_values()
                ->join('stars', 'review_value_news.star_id', '=', 'stars.id')
                ->distinct('stars.value')
                ->avg('stars.value') ?? 0,
            1
        );
    }



    public function scopeWhereMeetsThreshold($query, $is_staff_review = 0)
    {
        // Fallback global threshold if business column is null
        $globalThreshold = (float) config('ai.sentiment.thresholds.csat', 4.0);

        return $query->whereExists(function ($subQuery) use ($globalThreshold, $is_staff_review) {
            $subQuery->select(DB::raw(1))
                ->from('review_value_news as rvn')
                ->join('questions as q', 'rvn.question_id', '=', 'q.id')
                // Join businesses to access the threshold_rating column
                ->join('businesses as b', 'review_news.business_id', '=', 'b.id')

                ->when((request()->has('staff_id') || $is_staff_review), function ($q) {
                    $q->whereExists(function ($categoryQuery) {
                        $categoryQuery->select(DB::raw(1))
                            ->from('q_q_sub_categories as qqsc')
                            ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                            ->join('question_categories as qc_parent', 'qc_sub.parent_id', '=', 'qc_parent.id')
                            ->whereColumn('qqsc.question_id', 'q.id')
                            ->where('qc_parent.title', 'Staff')
                            ->where('qc_parent.is_active', 1)
                            ->where('qc_parent.is_default', 1)
                            ->whereNull('qc_parent.business_id')
                            ->where('qc_sub.is_active', 1);
                    });
                })

                ->join('stars as s', 'rvn.star_id', '=', 's.id')
                ->whereColumn('rvn.review_id', 'review_news.id')
                ->groupBy('rvn.review_id', 'b.threshold_rating')
                /* We use COALESCE so if threshold_rating is NULL,
               it defaults to your global config value.
            */
                ->havingRaw('AVG(s.value) >= COALESCE(b.threshold_rating, ?)', [$globalThreshold]);
        });
    }


    public function scopeWhereDoesNotMeetsThreshold($query, $is_staff_review = 0)
    {
        // Get global threshold rating from AI config
        $thresholdRating = (float) config('ai.sentiment.thresholds.csat', 4.0);

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


    // In your QuestionValue model (or the model that has 'value' relation)
    public function scopeFilterByOverall($query, $is_overall)
    {
        return $query->where('is_overall', $is_overall ? 1 : 0);
    }


    public function isVoiceReview()
    {
        return $this->is_voice_review;
    }




    public function scopeFilterByDateRange($query)
    {
        $query->when(request()->filled('start_date'), function ($q) {
            $q->whereDate('review_news.created_at', '>=', Carbon::parse(request()->input('start_date'))->startOfDay());
        })
            ->when(request()->filled('end_date'), function ($q) {
                $q->whereDate('review_news.created_at', '<=', Carbon::parse(request()->input('end_date'))->endOfDay());
            })
            ->when(request()->filled('period'), function ($q) {
                $dateRange = getDateRangeByPeriod(request()->input('period'));
                $q->whereBetween('review_news.created_at', [$dateRange['start'], $dateRange['end']]);
            });


        return $query;
    }

    public function scopeFilterBySentimentScore($query)
    {
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $query->when(request()->has('sentiment_score') && !empty(request()->input('sentiment_score')), function ($q) use ($positiveThreshold, $negativeThreshold) {
            $sentimentScore = request()->input('sentiment_score');

            if ($sentimentScore === 'positive') {
                $q->where('review_news.sentiment_score', '>=', $positiveThreshold);
            } elseif ($sentimentScore === 'negative') {
                $q->where('review_news.sentiment_score', '<', $negativeThreshold);
            } elseif ($sentimentScore === 'neutral') {
                $q->where('review_news.sentiment_score', '>=', $negativeThreshold)
                    ->where('review_news.sentiment_score', '<', $positiveThreshold);
            }
        });

        return $query;
    }

    public function scopeFilterByQuestionCategory($query)
    {
        $query->when(request()->filled("question_category_id") || request()->filled("question_sub_category_id"), function ($q) {
            $q->whereHas("value", function ($q) {
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
        });

        return $query;
    }

    public function scopeFilterByBusinessArea($query)
    {
        $query->when(request()->filled("business_area_id"), function ($q) {
            $q->whereHas("review_business_services", function ($q) {
                $q->where("review_business_services.business_area_id", request()->input("business_area_id"));
            });
        });

        return $query;
    }

    public function scopeFilterByBusinessService($query)
    {
        $query->when(request()->filled("business_service_id"), function ($q) {
            $q->whereHas("review_business_services", function ($q) {
                $q->where("review_business_services.business_service_id", request()->input("business_service_id"));
            });
        });

        return $query;
    }

    public function scopeFilterByStaff($query)
    {
        $query
            // Apply single staff_id filter
            ->when(request()->has('staff_id'), function ($q) {
                $q->where('review_news.staff_id', request()->input('staff_id'));
            })
            // Apply multiple staff_ids filter (comma-separated)
            ->when(request()->has('staff_ids') && !empty(request()->input('staff_ids')), function ($q) {
                $staffIds = request()->input('staff_ids');

                // Handle both array and comma-separated string
                if (is_string($staffIds)) {
                    $staffIds = array_map('trim', explode(',', $staffIds));
                }

                // Filter out non-numeric values and convert to integers
                $staffIds = array_filter(array_map('intval', $staffIds), function ($id) {
                    return $id > 0;
                });

                if (!empty($staffIds)) {
                    $q->whereIn('review_news.staff_id', $staffIds);
                }
            });

        return $query;
    }

    public function scopeFilterByIsOverall($query)
    {
        $query->when(request()->filled('is_overall'), function ($q) {
            $q->when(request()->boolean('is_overall'), function ($q) {
                $q->where('review_news.is_overall', 1);
            }, function ($q) {
                $q->where('review_news.is_overall', 0);
            });
        });

        return $query;
    }

    public function scopeFilterByIsVoiceReview($query)
    {
        $query->when(request()->has('is_voice_review'), function ($q) {
            $q->where('review_news.is_voice_review', request()->input('is_voice_review'));
        });

        return $query;
    }

    public function scopeFilterByReviewIds($query)
    {
        $query->when(request()->has('review_ids') && !empty(request()->input('review_ids')), function ($q) {
            $reviewIds = request()->input('review_ids');

            // Handle both array and comma-separated string
            if (is_string($reviewIds)) {
                $reviewIds = array_filter(array_map('trim', explode(',', $reviewIds)));
            }

            if (!empty($reviewIds)) {
                $q->whereIn('review_news.id', $reviewIds);
            }
        });

        return $query;
    }

    public function scopeFilterByTopics($query)
    {
        $query->when(request()->has('topics') && !empty(request()->input('topics')), function ($q) {
            $topic = request()->input('topics');
            // Use whereRaw with JSON_SEARCH to search within the main_category field
            $q->whereRaw("JSON_SEARCH(review_news.topics, 'one', ?, null, '$[*].main_category') IS NOT NULL", [$topic]);
        });

        return $query;
    }

    public function scopeFilterBySurveyIds($query)
    {
        $query->when(request()->has('survey_ids') && !empty(request()->input('survey_ids')), function ($q) {
            $surveyIds = request()->input('survey_ids');

            // Handle both array and comma-separated string
            if (is_string($surveyIds)) {
                $surveyIds = array_map('trim', explode(',', $surveyIds));
            }

            // Filter out non-numeric values and convert to integers
            $surveyIds = array_filter(array_map('intval', $surveyIds), function ($id) {
                return $id > 0;
            });

            if (!empty($surveyIds)) {
                $q->whereIn('review_news.survey_id', $surveyIds);
            }
        });

        return $query;
    }

    public function scopeFilterByTagIds($query)
    {
        $query->when(request()->has('tag_ids') && !empty(request()->input('tag_ids')), function ($q) {
            $tagIds = request()->input('tag_ids');

            // Handle both array and comma-separated string
            if (is_string($tagIds)) {
                $tagIds = array_map('trim', explode(',', $tagIds));
            }

            // Filter out non-numeric values and convert to integers
            $tagIds = array_filter(array_map('intval', $tagIds), function ($id) {
                return $id > 0;
            });

            if (!empty($tagIds)) {
                $q->whereHas('value', function ($valueQuery) use ($tagIds) {
                    $valueQuery->whereHas('tags', function ($tagQuery) use ($tagIds) {
                        $tagQuery->whereIn('tags.id', $tagIds);
                    });
                });
            }
        });

        return $query;
    }

    public function scopeFilterByStarIds($query)
    {
        $query->when(request()->has('star_ids') && !empty(request()->input('star_ids')), function ($q) {
            $starIds = request()->input('star_ids');

            // Handle both array and comma-separated string
            if (is_string($starIds)) {
                $starIds = array_map('trim', explode(',', $starIds));
            }

            // Filter out non-numeric values and convert to integers
            $starIds = array_filter(array_map('intval', $starIds), function ($id) {
                return $id > 0;
            });

            if (!empty($starIds)) {
                $q->whereHas('value', function ($valueQuery) use ($starIds) {
                    $valueQuery->whereIn('review_value_news.star_id', $starIds);
                });
            }
        });

        return $query;
    }

    public function scopeGlobalReviewFilters($query, $show_published_only = 0, $is_staff_review = 0, $turn_off_branch_filter = 0)
    {
        // Apply branch filter - GET AUTHENTICATED USER FROM REQUEST (NOT QUERY)
        $userBranchId = request()->user() && (request()->user()->hasRole('branch_manager') || request()->user()->hasRole('business_owner'))
            ? request()->user()->default_branch_id
            : null;


        $query
            // Apply is_overall filter using dedicated scope
            ->filterByIsOverall()
            // Apply staff filter using dedicated scope
            ->filterByStaff()
            // Apply is_voice_review filter using dedicated scope
            ->filterByIsVoiceReview()
            // Apply question category filter using dedicated scope
            ->filterByQuestionCategory()
            // Apply business area filter using dedicated scope
            ->filterByBusinessArea()
            // Apply business service filter using dedicated scope
            ->filterByBusinessService()
            ->when($show_published_only, function ($q) use ($is_staff_review) {
                $q->whereMeetsThreshold($is_staff_review);
            })
            ->when(request()->has('meets_threshold'), function ($q) use ($is_staff_review) {
                $meetsThreshold = request()->input('meets_threshold');
                if ($meetsThreshold == 1) {
                    $q->whereMeetsThreshold($is_staff_review);
                } elseif ($meetsThreshold == 0) {
                    $q->whereDoesNotMeetsThreshold($is_staff_review);
                }
            })
            ->when(request()->has('has_staff'), function ($q) {
                $hasStaff = (int) request()->input('has_staff');
                if ($hasStaff === 1) {
                    $q->whereNotNull('review_news.staff_id');
                } elseif ($hasStaff === 0) {
                    $q->whereNull('review_news.staff_id');
                }
            })
            ->when($userBranchId && $turn_off_branch_filter == 0, function ($q) use ($userBranchId) {
                $q->where("review_news.branch_id", $userBranchId);
            });

        return $query;
    }



    public function scopeReviewFilters($query)
    {
        $query // Apply sentiment score filter using dedicated scope
            ->filterBySentimentScore()
            // Apply review_ids filter using dedicated scope
            ->filterByReviewIds()
            // Apply topics filter using dedicated scope
            ->filterByTopics()
            // Apply survey_ids filter using dedicated scope
            ->filterBySurveyIds()
            // Apply tag_ids filter using dedicated scope
            ->filterByTagIds()
            // Apply star_ids filter using dedicated scope
            ->filterByStarIds();

        return $query;
    }
}
