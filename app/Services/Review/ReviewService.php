<?php

namespace App\Services\Review;

use App\Models\Branch;
use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Tag;
use App\Models\User;
use App\Models\Star;
use App\Services\Notification\NotificationService;
use App\Services\Rule\RuleEngineService;
use App\Services\Business\BusinessAnalyticsService;
use Carbon\Carbon;
use DB;

class ReviewService
{

    private NotificationService $notificationService;

    public function __construct(
        NotificationService $notificationService
    ) {

        $this->notificationService = $notificationService;
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

        $query = ReviewNew::where('business_id', $businessId)
            ->globalReviewFilters(0)
            ->reviewFilters()
            ->filterByDateRange();

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

        $query = ReviewNew::where('business_id', $businessId)
            ->globalReviewFilters(0)
            ->reviewFilters();

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
     * Extract rating breakdown from reviews or fetch them by business ID
     */
    public function extractRatingBreakdown($reviewsOrBusinessId, $dateRange = null, $user = null)
    {
        $reviews = $reviewsOrBusinessId;

        // If it's a numeric ID, fetch the reviews first
        if (is_numeric($reviewsOrBusinessId)) {
            $businessId = $reviewsOrBusinessId;
            $userBranchId = ($user && ($user->hasRole('branch_manager') || $user->hasRole('business_owner')))
                ? $user->default_branch_id
                : null;

            $query = ReviewNew::where('business_id', $businessId)
                ->globalReviewFilters(0)
                ->withCalculatedRating();

            if ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            }

            if ($userBranchId) {
                $query->where('branch_id', $userBranchId);
            }

            $reviews = $query->get();
        }

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
                $roundedRating = (string) round($rating);
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

        // Build WHERE conditions for filters
        $whereConditions = ['(t.business_id = ? OR t.business_id IS NULL)'];
        $bindings = [$businessId];

        if ($dateRange) {
            $whereConditions[] = 'r.created_at BETWEEN ? AND ?';
            $bindings[] = $dateRange['start'];
            $bindings[] = $dateRange['end'];
        }

        if ($userBranchId) {
            $whereConditions[] = 'r.branch_id = ?';
            $bindings[] = $userBranchId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // OPTIMIZED: Single query with JOIN and GROUP BY
        $tagsData = DB::select("
            SELECT
                t.id,
                t.tag,
                COUNT(DISTINCT rvt.review_value_id) as mention_count
            FROM tags t
            LEFT JOIN review_value_tag rvt ON t.id = rvt.tag_id
            LEFT JOIN review_value_news rv ON rvt.review_value_id = rv.id
            LEFT JOIN review_news r ON rv.review_id = r.id
            WHERE {$whereClause}
            GROUP BY t.id, t.tag
            HAVING mention_count > 0
            ORDER BY mention_count DESC
        ", $bindings);

        // Convert to collection for easier manipulation
        $tags = collect($tagsData);
        $totalTagMentions = $tags->sum('mention_count');

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
            $count = $tag->mention_count;
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
                'tags_with_mentions' => $tags->where('mention_count', '>', 0)->count(),
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
     * Calculate average star value from review values
     */
    private function calculateAverageStarValue(array $values): ?float
    {
        $starIds = collect($values)
            ->pluck('star_id')
            ->filter()
            ->unique()
            ->values();

        if ($starIds->isEmpty()) {
            return null;
        }

        $starValuesById = Star::whereIn('id', $starIds)->pluck('value', 'id');

        $ratings = collect($values)
            ->pluck('star_id')
            ->filter()
            ->map(fn($starId) => $starValuesById[$starId] ?? null)
            ->filter();

        return $ratings->isNotEmpty() ? round($ratings->avg(), 1) : null;
    }

    /**
     * Store review values (question/star)
     */
    public function storeReviewValues($review, $values, $business)
    {
        $averageRating = $this->calculateAverageStarValue($values);

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
            $review->save();

            // Do not classify comment-only/no-star reviews as low-rating reviews.
            if ($averageRating === null) {
                return;
            }

            $thresholdRating = $business->threshold_rating ?? RuleEngineService::getCsatThreshold();

            // Get branch manager if review has branch_id
            $branchManagerId = null;
            if ($review->branch_id) {
                $branch = Branch::findOrFail($review->branch_id);
                $branchManagerId = $branch?->manager_id;
            }

            if ($averageRating >= $thresholdRating) {
                // Send notification only to branch manager
                if ($branchManagerId) {
                    // In-app notification
                    $this->notificationService->send_notification([
                        'receiver_id' => $branchManagerId,
                        'business_id' => $business->id,
                        'type' => 'new_review',
                        'title' => 'New Review Received',
                        'message' => "A new review with rating {$averageRating} has been submitted.",
                        'entity_id' => $review->id,
                        'priority' => 'normal',
                    ]);

                    // Mail notification
                    $manager = User::find($branchManagerId);
                    if ($manager && $manager->email) {
                        try {
                            \Illuminate\Support\Facades\Mail::to($manager->email)
                                ->send(new \App\Mail\ReviewNotificationMail(
                                    'New Review Received',
                                    "A new review with rating {$averageRating} has been submitted.",
                                    $averageRating,
                                    $business->name ?? null,
                                    $manager->first_Name ?? $manager->name ?? 'Manager'
                                ));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send email notification: ' . $e->getMessage());
                        }
                    }

                    // Push notification (with error handling)
                    try {
                        $this->notificationService->sendNotificationToFirebaseUser(
                            userId: $branchManagerId,
                            title: 'New Review Received',
                            body: "A new review with rating {$averageRating} has been submitted.",
                            data: [
                                'type' => 'new_review',
                                'entity_id' => (string) $review->id,
                                'rating' => (string) $averageRating,
                            ],
                        );
                    } catch (\Exception $e) {
                        log_message([
                            'message' => 'Failed to send push notification to branch manager',
                            'user_id' => $branchManagerId,
                            'review_id' => $review->id,
                            'error' => $e->getMessage()
                        ], 'firebase.log');
                    }
                } else {
                    $ownerId = $business->OwnerID;

                    // In-app notification
                    $this->notificationService->send_notification([
                        'receiver_id' => $ownerId,
                        'business_id' => $business->id,
                        'type' => 'new_review',
                        'title' => 'New Review Received',
                        'message' => "A new review with rating {$averageRating} has been submitted.",
                        'entity_id' => $review->id,
                        'priority' => 'normal',
                    ]);

                    // Mail notification
                    $owner = User::find($ownerId);
                    if ($owner && $owner->email) {
                        try {
                            \Illuminate\Support\Facades\Mail::to($owner->email)
                                ->send(new \App\Mail\ReviewNotificationMail(
                                    'New Review Received',
                                    "A new review with rating {$averageRating} has been submitted.",
                                    $averageRating,
                                    $business->name ?? null,
                                    $owner->first_Name ?? $owner->name ?? 'Owner'
                                ));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send email notification: ' . $e->getMessage());
                        }
                    }

                    // Push notification (with error handling)
                    try {
                        $this->notificationService->sendNotificationToFirebaseUser(
                            userId: $ownerId,
                            title: 'New Review Received',
                            body: "A new review with rating {$averageRating} has been submitted.",
                            data: [
                                'type' => 'new_review',
                                'entity_id' => (string) $review->id,
                                'rating' => (string) $averageRating,
                            ],
                        );
                    } catch (\Exception $e) {
                        log_message([
                            'message' => 'Failed to send push notification to owner',
                            'user_id' => $ownerId,
                            'review_id' => $review->id,
                            'error' => $e->getMessage()
                        ], 'firebase.log');
                    }
                }
            } else {
                // Send notification to both business owner and branch manager
                $receiverIds = [];

                if ($business->OwnerID) {
                    $receiverIds[] = $business->OwnerID;
                }

                if ($branchManagerId && $branchManagerId !== $business->OwnerID) {
                    $receiverIds[] = $branchManagerId;
                }

                foreach ($receiverIds as $receiverId) {
                    // In-app notification
                    $this->notificationService->send_notification([
                        'receiver_id' => $receiverId,
                        'business_id' => $business->id,
                        'type' => 'low_rating_review',
                        'title' => 'Low Rating Review Alert',
                        'message' => "A review with rating {$averageRating} (below threshold {$thresholdRating}) has been submitted.",
                        'entity_id' => $review->id,
                        'priority' => 'high',
                    ]);

                    // Mail notification
                    $user = User::find($receiverId);
                    if ($user && $user->email) {
                        try {
                            \Illuminate\Support\Facades\Mail::to($user->email)
                                ->send(new \App\Mail\ReviewNotificationMail(
                                    'Low Rating Review Alert',
                                    "A review with rating {$averageRating} (below threshold {$thresholdRating}) has been submitted.",
                                    $averageRating,
                                    $business->name ?? null,
                                    $user->first_Name ?? $user->name ?? 'User'
                                ));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send email notification: ' . $e->getMessage());
                        }
                    }

                    // Push notification for LOW RATINGS (with error handling)
                    try {
                        $this->notificationService->sendNotificationToFirebaseUser(
                            userId: $receiverId,
                            title: 'Low Rating Review Alert',
                            body: "A review with rating {$averageRating} (below threshold {$thresholdRating}) has been submitted.",
                            data: [
                                'type' => 'low_rating_review',
                                'entity_id' => (string) $review->id,
                                'rating' => (string) $averageRating,
                                'threshold' => (string) $thresholdRating,
                            ],
                        );
                    } catch (\Exception $e) {
                        log_message([
                            'message' => 'Failed to send low rating push notification',
                            'user_id' => $receiverId,
                            'review_id' => $review->id,
                            'error' => $e->getMessage()
                        ], 'firebase.log');
                    }
                }
            }
        }

        // NO LONGER CALCULATE AND STORE THE AVERAGE HERE
        // Ratings will be calculated on-the-fly from ReviewValue data
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

            // Count-based sentiment aggregation
            $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
            $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

            $positiveCount = $staffReviews->where('sentiment_score', '>=', $positiveThreshold)->count();
            $negativeCount = $staffReviews->where('sentiment_score', '<', $negativeThreshold)->count();
            $neutralCount = $staffReviews->count() - $positiveCount - $negativeCount;

            return [
                'staff_id' => $staffId,
                'staff_name' => $staff->name,
                'position' => $staff->job_title ?? 'Staff',
                'avg_rating' => round($avgRating, 1),
                'total_reviews' => $staffReviews->count(),
                'sentiment_score' => RuleEngineService::determineAggregatedLabel($positiveCount, $neutralCount, $negativeCount),
                'image' => $staff->image ?? null
            ];
        })
            ->filter(function ($staff) {
                return $staff && $staff['total_reviews'] >= RuleEngineService::getMinReviewsStaffAnalysis();
            })
            ->sortByDesc('avg_rating')
            ->take($limit)
            ->values()
            ->toArray();

        return $staffRatings;
    }
}
