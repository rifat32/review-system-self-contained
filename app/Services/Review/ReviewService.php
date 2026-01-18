<?php

namespace App\Services\Review;

use App\Models\Branch;
use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Tag;
use App\Models\User;
use App\Services\Rule\RuleEngineService;
use App\Services\Business\BusinessAnalyticsService;
use Carbon\Carbon;

class ReviewService
{
    private RuleEngineService $ruleEngineService;
    private BusinessAnalyticsService $businessAnalyticsService;
    private ReviewTopicService $reviewTopicService;

    public function __construct(
        RuleEngineService $ruleEngineService,
        BusinessAnalyticsService $businessAnalyticsService,
        ReviewTopicService $reviewTopicService
    ) {
        $this->ruleEngineService = $ruleEngineService;
        $this->businessAnalyticsService = $businessAnalyticsService;
        $this->reviewTopicService = $reviewTopicService;
    }
    /**
     * Get current period reviews
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @param bool $withCalculatedRating Whether to include calculated rating
     * @return \Illuminate\Support\Collection
     */
    public function getCurrentPeriodReviews(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null,
        bool $withCalculatedRating = true
    ) {
        $query = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId);

        // Apply branch filter if provided
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Apply date range filter if provided
        if ($dateRange !== null) {
            $startDate = Carbon::parse($dateRange['start'])->startOfDay();
            $endDate = Carbon::parse($dateRange['end'])->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Add calculated rating if requested
        if ($withCalculatedRating) {
            $query->withCalculatedRating();
        }

        return $query->get();
    }

    /**
     * Get comparison (previous) period reviews
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end', 'daysOffset'
     * @param bool $withCalculatedRating Whether to include calculated rating
     * @return \Illuminate\Support\Collection
     */
    public function getComparisonPeriodReviews(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null,
        bool $withCalculatedRating = true,
    ) {
        // Return empty collection if no date range provided
        if ($dateRange === null) {
            return collect();
        }

        $query = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId);

        // Apply branch filter if provided
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Calculate previous period dates
        $prevStartDate = Carbon::parse($dateRange['start'])->subDays($dateRange['daysOffset'])->startOfDay();
        $prevEndDate = Carbon::parse($dateRange['end'])->subDays($dateRange['daysOffset'])->endOfDay();

        $query->whereBetween('created_at', [$prevStartDate, $prevEndDate]);

        // Add calculated rating if requested
        if ($withCalculatedRating) {
            $query->withCalculatedRating();
        }

        return $query->get();
    }

    /**
     * Get reviews for a specific time period with flexible filtering
     * 
     * @param int $businessId
     * @param array $filters Additional filters ['branch_id' => int, 'staff_id' => int, etc.]
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @param bool $withCalculatedRating Whether to include calculated rating
     * @param array $with Relations to eager load
     * @return \Illuminate\Support\Collection
     */
    public function getReviewsWithFilters(
        int $businessId,
        array $filters = [],
        ?array $dateRange = null,
        bool $withCalculatedRating = true,
        array $with = []
    ) {
        $query = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId);

        // Apply additional filters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        // Apply date range filter if provided
        if ($dateRange !== null) {
            $startDate = Carbon::parse($dateRange['start'])->startOfDay();
            $endDate = Carbon::parse($dateRange['end'])->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Add calculated rating if requested
        if ($withCalculatedRating) {
            $query->withCalculatedRating();
        }

        // Eager load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get both current and comparison period reviews
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @param int $comparisonDaysOffset Number of days to go back for comparison (default 30)
     * @return array ['current' => Collection, 'previous' => Collection]
     */
    public function getCurrentAndComparisonReviews(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null,
        bool $withCalculatedRating = true,
    ): array {
        return [
            'current' => $this->getCurrentPeriodReviews(
                businessId: $businessId,
                branchId: $branchId,
                dateRange: $dateRange,
                withCalculatedRating: $withCalculatedRating
            ),
            'previous' => $this->getComparisonPeriodReviews(
                businessId: $businessId,
                branchId: $branchId,
                dateRange: $dateRange,
                withCalculatedRating: $withCalculatedRating
            )
        ];
    }

    // ========== METHODS FROM HELPER REFACTORING ==========

    /**
     * Calculate percentage change between current and previous values
     */
    public function calculatePercentageChange($current, $previous)
    {
        if ($previous == 0) {
            return 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Extract rating breakdown from reviews
     */
    public function extractRatingBreakdown($reviews)
    {
        $breakdown = [
            'excellent' => 0, // 4.5-5.0
            'good' => 0,      // 3.5-4.49
            'average' => 0,   // 2.5-3.49
            'poor' => 0,      // 1.5-2.49
            'very_poor' => 0, // 0-1.49
            'exact_ratings' => [
                '5' => 0,
                '4' => 0,
                '3' => 0,
                '2' => 0,
                '1' => 0
            ]
        ];

        $totalRating = 0;
        $validReviews = 0;

        foreach ($reviews as $review) {
            $rating = $review->calculated_rating ?? 0;

            if ($rating > 0) {
                $totalRating += $rating;
                $validReviews++;

                // Detailed breakdown
                if ($rating >= 4.5) {
                    $breakdown['excellent']++;
                } elseif ($rating >= 3.5) {
                    $breakdown['good']++;
                } elseif ($rating >= 2.5) {
                    $breakdown['average']++;
                } elseif ($rating >= 1.5) {
                    $breakdown['poor']++;
                } else {
                    $breakdown['very_poor']++;
                }

                // Exact ratings
                $roundedRating = round($rating);
                if (isset($breakdown['exact_ratings'][$roundedRating])) {
                    $breakdown['exact_ratings'][$roundedRating]++;
                }
            }
        }

        return [
            'breakdown' => $breakdown,
            'average_rating' => $validReviews > 0 ? round($totalRating / $validReviews, 1) : 0,
            'total_reviews' => $reviews->count(),
            'valid_reviews' => $validReviews
        ];
    }

    /**
     * Extract tags breakdown with mentions and percentages
     */
    public function extractTagsBreakdown($businessId, $dateRange, $user = null)
    {
        // Determine branch filter for branch managers
        $userBranchId = ($user && ($user->hasRole('branch_manager') || $user->hasRole('business_owner')))
            ? $user->default_branch_id
            : null;

        // Get all tags with their mention counts
        $tags = Tag::where('business_id', $businessId)
            ->withCount([
                'review_values' => function ($query) use ($dateRange, $userBranchId) {
                    $query->whereHas('review', function ($q) use ($dateRange, $userBranchId) {
                        // Apply date range filter if provided
                        $q->when($dateRange, function ($q) use ($dateRange) {
                            $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                        });

                        // Apply branch filter if provided
                        $q->when($userBranchId, function ($q) use ($userBranchId) {
                            $q->where('branch_id', $userBranchId);
                        });
                    });
                }
            ])
            ->when($dateRange || $userBranchId, function ($query) use ($dateRange, $userBranchId) {
                // Only include tags that have at least one matching review
                $query->whereHas('review_values', function ($q) use ($dateRange, $userBranchId) {
                    $q->whereHas('review', function ($q) use ($dateRange, $userBranchId) {
                        $q->when($dateRange, function ($q) use ($dateRange) {
                            $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                        });
                        $q->when($userBranchId, function ($q) use ($userBranchId) {
                            $q->where('branch_id', $userBranchId);
                        });
                    });
                });
            })
            ->orderByDesc('review_values_count')
            ->get();

        $totalTagMentions = $tags->sum('review_values_count');

        // Color palette for visualization
        $colorPalette = [
            '#3490dc',
            '#38c172',
            '#e3342f',
            '#f6993f',
            '#9561e2',
            '#ffed4a',
            '#4dc0b5',
            '#f66d9b',
            '#6574cd',
            '#a0aec0'
        ];

        // Prepare breakdown
        $breakdown = $tags->map(function ($tag, $index) use ($totalTagMentions, $colorPalette) {
            $count = $tag->review_values_count;
            $percentage = $totalTagMentions > 0 ? round(($count / $totalTagMentions) * 100) : 0;
            $colorIndex = $index % count($colorPalette);

            return [
                'id' => $tag->id,
                'tag' => $tag->tag,
                'color' => $colorPalette[$colorIndex],
                'count' => $count,
                'percentage' => $percentage,
                'display_text' => "{$tag->tag} ({$percentage}%)"
            ];
        });

        $topTags = $breakdown->take(8)->values();

        return [
            'tags' => $topTags->toArray(),
            'summary' => [
                'total_tags' => $tags->count(),
                'total_tag_mentions' => $totalTagMentions,
                'tags_with_mentions' => $tags->where('review_values_count', '>', 0)->count(),
                'top_tag' => $topTags->isNotEmpty() ? $topTags->first()['tag'] : null,
                'top_tag_percentage' => $topTags->isNotEmpty() ? $topTags->first()['percentage'] : 0,
                'average_mentions_per_tag' => $tags->count() > 0 ? round($totalTagMentions / $tags->count(), 1) : 0
            ],
            'all_tags_count' => $tags->count(),
            'visualization_data' => [
                'labels' => $topTags->pluck('tag')->toArray(),
                'datasets' => [
                    [
                        'data' => $topTags->pluck('percentage')->toArray(),
                        'backgroundColor' => $topTags->pluck('color')->toArray(),
                        'borderWidth' => 1
                    ]
                ]
            ]
        ];
    }

    /**
     * Format period display for date ranges
     */
    public function formatPeriodDisplay($startDate, $endDate)
    {
        // Check if it's all-time data (start date is very old)
        $veryOldDate = Carbon::createFromTimestamp(0)->addDay();

        if ($startDate->lessThan($veryOldDate)) {
            return 'All Time';
        }

        $startFormatted = $startDate->format('M j, Y');
        $endFormatted = $endDate->format('M j, Y');

        // If same day
        if ($startDate->isSameDay($endDate)) {
            return $startDate->format('M j, Y');
        }

        // If same month
        if ($startDate->format('Y-m') === $endDate->format('Y-m')) {
            return $startDate->format('M j') . ' - ' . $endDate->format('j, Y');
        }

        // If same year
        if ($startDate->format('Y') === $endDate->format('Y')) {
            return $startDate->format('M j') . ' - ' . $endDate->format('M j, Y');
        }

        return $startFormatted . ' - ' . $endFormatted;
    }

    /**
     * Calculate dashboard metrics
     */
    public function calculateDashboardMetrics($businessId, $dateRange = null)
    {
        // Get current period reviews WITH calculated rating
        $reviewsQuery = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId)
            ->withCalculatedRating();

        // Apply date filter only if dateRange is provided
        if ($dateRange !== null) {
            $reviewsQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $reviews = $reviewsQuery->get();

        // Get previous period reviews WITH calculated rating (only if dateRange provided)
        $previousReviews = collect();
        $previousAvgRating = 0;
        $previousTotal = 0;
        $previous_sentiment_score = 0;

        if ($dateRange !== null) {
            $previousReviews = ReviewNew::globalFilters(0, $businessId)
                ->where('business_id', $businessId)
                ->whereBetween('created_at', [
                    $dateRange['start']->copy()->subDays(30),
                    $dateRange['end']->copy()->subDays(30)
                ])
                ->globalFilters(0, $businessId)
                ->withCalculatedRating()
                ->get();

            $previousTotal = $previousReviews->count();

            // Calculate previous period ratings FROM calculated_rating field
            $previousAvgRating = $previousReviews->isNotEmpty()
                ? round($previousReviews->avg('calculated_rating'), 1)
                : 0;

            // Calculate sentiment scores (still from ReviewNew)
            $previous_sentiment_score = $previousReviews->avg('sentiment_score') ?? 0;
        }

        $total = $reviews->count();

        // Calculate current period ratings FROM calculated_rating field
        $currentAvgRating = $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;

        // Calculate sentiment scores (still from ReviewNew)
        $current_sentiment_score = $reviews->avg('sentiment_score') ?? 0;

        // Calculate positive/negative counts based on calculated_rating
        $positiveReviewsCount = $reviews->where('calculated_rating', '>=', 4)->count();
        $negativeReviewsCount = $reviews->where('calculated_rating', '<=', 2)->count();

        // Top Topic (minimal summary)
        $topTopicSummary = $this->reviewTopicService->getTopTopicSummary($reviews);

        // Detect repeated issues (minimal data only)
        $businessId = $reviews->first()->business_id ?? 0;
        $issueAnalysis = $this->businessAnalyticsService->extractIssuesFromRuleEngine(
            $businessId,
            $reviews,
            [
                'start' => $reviews->min('created_at') ?? now()->subMonth(),
                'end' => $reviews->max('created_at') ?? now()
            ]
        );

        // Calculate issue count and top issue correctly
        $isDefaultMessage = (count($issueAnalysis) === 1 && isset($issueAnalysis[0]['issue']) && str_starts_with(trim($issueAnalysis[0]['issue']), 'No major issues'));

        $totalIssues = $isDefaultMessage ? 0 : count($issueAnalysis);
        $topIssue = $isDefaultMessage ? null : ($issueAnalysis[0]['issue'] ?? null);

        return [
            'avg_overall_rating' => [
                'value' => $currentAvgRating,
                'change' => $dateRange !== null ? $this->calculatePercentageChange(
                    $currentAvgRating,
                    $previousAvgRating
                ) : null,
                'previous_value' => $previousAvgRating,
                'calculated_from' => 'review_value_news (via calculated_rating)',
                'review_count' => $total
            ],
            'ai_sentiment_score' => [
                'value' => round($current_sentiment_score * 10, 1),
                'max' => 10,
                'change' => $dateRange !== null ? $this->calculatePercentageChange(
                    $current_sentiment_score,
                    $previous_sentiment_score
                ) : null,
                'review_count' => $total
            ],
            'total_reviews' => [
                'value' => $total,
                'change' => $dateRange !== null ? $this->calculatePercentageChange($total, $previousTotal) : null
            ],
            'positive_negative_ratio' => [
                'positive' => $total > 0 ? round(($positiveReviewsCount / $total) * 100) : 0,
                'negative' => $total > 0 ? round(($negativeReviewsCount / $total) * 100) : 0,
                'positive_count' => $positiveReviewsCount,
                'negative_count' => $negativeReviewsCount,
                'review_count' => $total
            ],
            'staff_linked_reviews' => [
                'percentage' => $total > 0 ? round(($reviews->whereNotNull('staff_id')->count() / $total) * 100) : 0,
                'count' => $reviews->whereNotNull('staff_id')->count(),
                'total' => $total,
                'review_count' => $total
            ],
            'voice_reviews' => [
                'percentage' => $total > 0 ? round(($reviews->where('is_voice_review', true)->count() / $total) * 100) : 0,
                'count' => $reviews->where('is_voice_review', true)->count(),
                'total' => $total,
                'review_count' => $total
            ],
            'rating_distribution' => [
                '5_star' => $reviews->where('calculated_rating', '>=', 4.5)->count(),
                '4_star' => $reviews->whereBetween('calculated_rating', [4.0, 4.49])->count(),
                '3_star' => $reviews->whereBetween('calculated_rating', [3.0, 3.99])->count(),
                '2_star' => $reviews->whereBetween('calculated_rating', [2.0, 2.99])->count(),
                '1_star' => $reviews->where('calculated_rating', '<', 2.0)->count()
            ],
            'top_topic' => $topTopicSummary,
            'repeated_issues' => [
                'review_count' => $total,
                'issue_count' => $totalIssues,
                'top_issue' => $topIssue
            ]
        ];
    }

    /**
     * Store review values (question/star)
     */
    public function storeReviewValues($review, $values, $business)
    {
        $averageRating = collect($values)
            ->pluck('star_id')
            ->filter()
            ->avg();

        $averageRating = $averageRating ? round($averageRating, 1) : null;

        foreach ($values as $value) {
            // Extract tag_ids before creating (it's not a database column, it's a many-to-many relationship)
            $tagIds = $value['tag_ids'] ?? [];

            // Create review value without tag_ids (only fillable: question_id, star_id, review_id)
            $review_value = ReviewValueNew::create([
                'review_id' => $review->id,
                'question_id' => $value['question_id'],
                'star_id' => $value['star_id'],
            ]);

            // Sync tags via relationship
            $review_value->tags()->sync($tagIds);
        }


        if ($business && $review->guest_id) {
            // $notificationService = app(\App\Services\Notification\NotificationService::class);

            // Get branch manager if review has branch_id
            $branchManagerId = null;
            if ($review->branch_id) {
                $branch = Branch::find($review->branch_id);
                $branchManagerId = $branch?->manager_id;
            }

            if ($averageRating >= $business->threshold_rating) {
                $review->save();

                // Send notification only to branch manager
                if ($branchManagerId) {
                    // In-app notification
                    // $notificationService->send_notification([
                    //     'receiver_id' => $branchManagerId,
                    //     'business_id' => $business->id,
                    //     'type' => 'new_review',
                    //     'title' => 'New Review Received',
                    //     'message' => "A new review with rating {$averageRating} has been submitted.",
                    //     'entity_id' => $review->id,
                    //     'priority' => 'normal',
                    // ]);

                    // Push notification (with error handling)
                    // try {
                    //     $notificationService->sendNotificationToFirebaseUser(
                    //         userId: $branchManagerId,
                    //         title: 'New Review Received',
                    //         body: "A new review with rating {$averageRating} has been submitted.",
                    //         data: [
                    //             'type' => 'new_review',
                    //             'entity_id' => (string) $review->id,
                    //             'rating' => (string) $averageRating,
                    //         ],
                    //     );
                    // } catch (\Exception $e) {
                    //     \Log::warning('Failed to send push notification to branch manager', [
                    //         'user_id' => $branchManagerId,
                    //         'review_id' => $review->id,
                    //         'error' => $e->getMessage()
                    //     ]);
                    // }
                } else {
                    $ownerId = $business->OwnerID;

                    // In-app notification
                    // $notificationService->send_notification([
                    //     'receiver_id' => $ownerId,
                    //     'business_id' => $business->id,
                    //     'type' => 'new_review',
                    //     'title' => 'New Review Received',
                    //     'message' => "A new review with rating {$averageRating} has been submitted.",
                    //     'entity_id' => $review->id,
                    //     'priority' => 'normal',
                    // ]);

                    // Push notification (with error handling)
                    // try {
                    //     $notificationService->sendNotificationToFirebaseUser(
                    //         userId: $ownerId,
                    //         title: 'New Review Received',
                    //         body: "A new review with rating {$averageRating} has been submitted.",
                    //         data: [
                    //             'type' => 'new_review',
                    //             'entity_id' => (string) $review->id,
                    //             'rating' => (string) $averageRating,
                    //         ],
                    //     );
                    // } catch (\Exception $e) {
                    //     \Log::warning('Failed to send push notification to owner', [
                    //         'user_id' => $ownerId,
                    //         'review_id' => $review->id,
                    //         'error' => $e->getMessage()
                    //     ]);
                    // }
                }
            } else {
                $review->save();

                // Send notification to both business owner and branch manager
                $receiverIds = [];

                if ($business->OwnerID) {
                    $receiverIds[] = $business->OwnerID;
                }

                if ($branchManagerId && $branchManagerId !== $business->OwnerID) {
                    $receiverIds[] = $branchManagerId;
                }

                // foreach ($receiverIds as $receiverId) {
                //     // In-app notification
                //     // $notificationService->send_notification([
                //     //     'receiver_id' => $receiverId,
                //     //     'business_id' => $business->id,
                //     //     'type' => 'low_rating_review',
                //     //     'title' => 'Low Rating Review Alert',
                //     //     'message' => "A review with rating {$averageRating} (below threshold {$business->threshold_rating}) has been submitted.",
                //     //     'entity_id' => $review->id,
                //     //     'priority' => 'high',
                //     // ]);

                //     // Push notification for LOW RATINGS (with error handling)
                //     // try {
                //     //     $notificationService->sendNotificationToFirebaseUser(
                //     //         userId: $receiverId,
                //     //         title: 'Low Rating Review Alert',
                //     //         body: "A review with rating {$averageRating} (below threshold {$business->threshold_rating}) has been submitted.",
                //     //         data: [
                //     //             'type' => 'low_rating_review',
                //     //             'entity_id' => (string) $review->id,
                //     //             'rating' => (string) $averageRating,
                //     //             'threshold' => (string) $business->threshold_rating,
                //     //         ],
                //     //     );
                //     // } catch (\Exception $e) {
                //     //     \Log::warning('Failed to send low rating push notification', [
                //     //         'user_id' => $receiverId,
                //     //         'review_id' => $review->id,
                //     //         'error' => $e->getMessage()
                //     //     ]);
                //     // }
                // }
            }
        }

        // NO LONGER CALCULATE AND STORE THE AVERAGE HERE
        // Ratings will be calculated on-the-fly from ReviewValue data
    }

    /**
     * Get available filters for the business
     */
    public function getAvailableFilters($businessId)
    {
        return [
            'periods' => ['Last 30 Days', 'Last 7 Days', 'This Month', 'Last Month', 'Custom Range'],
            'staff' => array_merge(
                ['All Staff'],
                User::whereHas('staffReviews', fn($q) => $q->where('business_id', $businessId))
                    ->get()
                    ->map(fn($user) => $user->name)
                    ->toArray()
            ),
            'branches' => ['All Branches', 'Downtown', 'Uptown', 'Westside'],
            'review_types' => ['All Review Types', 'Text Only', 'Voice Only', 'Survey', 'Overall'],
            'ai_sentiment' => ['All Sentiments', 'Positive', 'Neutral', 'Negative', 'AI Flagged']
        ];
    }

    /**
     * Calculate response rate
     */
    public static function calculateResponseRate($reviews)
    {
        $total = $reviews->count();
        if ($total === 0) {
            return 0;
        }

        $responded = $reviews->whereNotNull('responded_at')->count();
        return round(($responded / $total) * 100, 1);
    }

    /**
     * Calculate tenure from join date
     */
    public function calculateTenure($joinDate)
    {
        if (!$joinDate) {
            return 'Not specified';
        }

        $join = Carbon::parse($joinDate);
        $now = Carbon::now();

        $years = $now->diffInYears($join);
        $months = $now->diffInMonths($join) % 12;

        return "{$years} years {$months} months";
    }

    /**
     * Get rating trend from review values
     */
    public function getRatingTrendFromReviewValue($reviews)
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        $monthlyReviews = $reviews->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy(function ($review) {
                return $review->created_at->format('Y-m');
            });

        $monthlyRatings = [];

        foreach ($monthlyReviews as $month => $monthReviews) {
            // Use calculated_rating field directly
            $monthlyRatings[$month] = $monthReviews->avg('calculated_rating') ?? 0;
        }

        ksort($monthlyRatings);

        return [
            'period' => 'last_6_months',
            'data' => $monthlyRatings,
            'trend_direction' => $this->calculateTrendDirection($monthlyRatings)
        ];
    }

    /**
     * Calculate trend direction from monthly ratings
     */
    public function calculateTrendDirection($monthlyRatings)
    {
        if (count($monthlyRatings) < 2) {
            return 'stable';
        }

        $values = array_values($monthlyRatings);
        $first = $values[0];
        $last = end($values);

        if ($last > $first + 0.3) {
            return 'improving';
        } elseif ($last < $first - 0.3) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Fill missing periods in data
     */
    public function fillMissingPeriods($data, $startDate, $endDate, $format)
    {
        $filledData = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodKey = $current->format($format);
            $filledData[$periodKey] = $data[$periodKey] ?? [
                'submissions_count' => 0,
                'average_rating' => 0,
                'sentiment_score' => 0
            ];

            if ($format === 'd-m-Y') {
                $current->addDay();
            } else {
                $current->addMonth();
            }
        }

        return $filledData;
    }

    /**
     * Apply filters to query
     */
    public function applyFilters($query, $filters)
    {
        // Survey filter
        if (!empty($filters['survey_id'])) {
            $query->where('survey_id', $filters['survey_id']);
        }

        // Guest reviews filter
        if (isset($filters['is_guest_review']) && $filters['is_guest_review'] === 'true') {
            $query->whereNotNull('guest_id');
        }

        // User reviews filter
        if (isset($filters['is_user_review']) && $filters['is_user_review'] === 'true') {
            $query->whereNotNull('user_id');
        }

        // Overall reviews filter
        if (isset($filters['is_overall']) && $filters['is_overall'] === 'true') {
            $query->where('is_overall', 1);
        } elseif (isset($filters['is_overall']) && $filters['is_overall'] === 'false') {
            $query->where('is_overall', 0);
        }

        // Staff filter
        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        // Score range filter
        if (!empty($filters['min_score']) || !empty($filters['max_score'])) {
            $query->withCalculatedRating();
            if (!empty($filters['min_score'])) {
                $query->having('calculated_rating', '>=', $filters['min_score']);
            }
            if (!empty($filters['max_score'])) {
                $query->having('calculated_rating', '<=', $filters['max_score']);
            }
        }

        // Labels filter (using sentiment field)
        if (!empty($filters['labels'])) {
            $labels = is_array($filters['labels']) ? $filters['labels'] : explode(',', $filters['labels']);
            $query->whereHas('value', function ($q) use ($labels) {
                $q->whereHas('tags', function ($q2) use ($labels) {
                    $q2->whereIn('tags.id', $labels);
                });
            });
        }

        // Review type filter (using review_type field)
        if (!empty($filters['review_type'])) {
            $query->where('review_type', $filters['review_type']);
        }

        // With comment or without comment
        if (isset($filters['has_comment']) && $filters['has_comment'] === 'true') {
            $query->whereNotNull('comment')->where('comment', '!=', '');
        } elseif (isset($filters['has_comment']) && $filters['has_comment'] === 'false') {
            $query->where(function ($q) {
                $q->whereNull('comment')->orWhere('comment', '');
            });
        }

        // Replied - yes or no
        if (isset($filters['has_reply']) && $filters['has_reply'] === 'true') {
            $query->whereNotNull('responded_at');
        } elseif (isset($filters['has_reply']) && $filters['has_reply'] === 'false') {
            $query->whereNull('responded_at');
        }

        return $query;
    }

    /**
     * Get top staff by rating from review values
     */
    public function getTopStaffByRatingFromReviewValue($reviews, $limit = 5)
    {
        $staffGroups = $reviews->groupBy('staff_id');

        $staffRatings = $staffGroups->map(function ($staffReviews, $staffId) {
            $staff = User::find($staffId);
            if (!$staff) {
                return null;
            }

            // Use calculated_rating field directly
            $avgRating = $staffReviews->avg('calculated_rating') ?? 0;

            return [
                'staff_id' => $staffId,
                'staff_name' => $staff->name,
                'position' => $staff->job_title ?? 'Staff',
                'avg_rating' => round($avgRating, 1),
                'total_reviews' => $staffReviews->count(),
                'sentiment_score' => RuleEngineService::getSentimentLabelFromScore(
                    score: $staffReviews->avg('sentiment_score') ?? 0.0
                ),
                'image' => $staff->image ?? null
            ];
        })
            ->filter(function ($staff) {
                return $staff && $staff['total_reviews'] >= 3;
            })
            ->sortByDesc('avg_rating')
            ->take($limit)
            ->values()
            ->toArray();

        return $staffRatings;
    }

    /**
     * Get user name from review
     */
    public function getUserName($review)
    {
        if ($review->user) {
            return $review->user->name;
        } elseif ($review->guest_user) {
            return $review->guest_user->full_name;
        } else {
            return 'Anonymous User';
        }
    }
}
