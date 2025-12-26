<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Question;
use App\Models\Business;

use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function generateDashboardReportV3unused(Request $request, $is_overall, $startDate, $endDate)
    {


        // Get reviews with calculated rating for the date range
        $reviews = (clone $review_query)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Calculate average rating from calculated_rating field
        $data["average_rating"] = $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;


        // Get distinct star ratings selected in reviews
        $total_stars_selected = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            filterByOverall($is_overall)
            ->select("review_value_news.star_id")
            ->distinct()
            ->get();

        // Loop through each distinct star selected
        foreach ($total_stars_selected as $key => $star_selected) {
            // Get the star details from the Star table
            $data["selected_stars"][$key]["star"] = Star::where([
                "id" => $star_selected->star_id
            ])
                ->filterByOverall($is_overall)
                ->first();

            // Count total times this star was selected overall
            $data["selected_stars"][$key]["star_selected_time"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "star_id" => $star_selected->star_id
                ])
                ->filterByOverall($is_overall)
                ->count();

            // Loop through each month to get monthly star selection counts
            for ($i = 0; $i <= $numberOfMonths; $i++) {
                // Start and end dates for the month (i months ago)
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
                $month = $startDateOfMonth->format('F');

                // Store the month name
                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["month"] = $month;

                // Count times this star was selected in the given month
                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["value"] = ReviewValueNew::
                    // whereMeetsThreshold($businessId)
                    where([
                        "star_id" => $star_selected->star_id
                    ])
                    ->whereBetween('review_value_news.created_at', [$startDateOfMonth, $endDateOfMonth])
                    ->filterByOverall($is_overall)
                    ->count();
            }

            // Count times this star was selected in the previous week
            $data["selected_stars"][$key]["star_selected_time_previous_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->filterByOverall($is_overall)
                ->count();

            // Count times this star was selected in the current week
            $data["selected_stars"][$key]["star_selected_time_this_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->filterByOverall($is_overall)
                ->count();
        }

        // Get all distinct tags selected in reviews
        $total_tag_selected = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            select("review_value_news.tag_id")
            ->filterByOverall($is_overall)
            ->distinct()
            ->get();

        // Loop through each distinct tag selected
        foreach ($total_tag_selected as $key => $tag_selected) {
            // Get the tag details from the Tag table
            $data["selected_tags"][$key]["tag"] = Tag::where([
                "id" => $tag_selected->tag_id
            ])
                ->filterByOverall($is_overall)
                ->first();

            // Count total times this tag was selected overall
            $data["selected_tags"][$key]["tag_selected_time"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->filterByOverall($is_overall)
                ->count();

            // Loop through each month to get monthly tag selection counts
            for ($i = 0; $i <= $numberOfMonths; $i++) {
                // Start and end dates for the month (i months ago)
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
                $month = $startDateOfMonth->format('F');

                // Store the month name
                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["month"] = $month;

                // Count times this tag was selected in the given month
                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["value"] = ReviewValueNew::
                    // whereMeetsThreshold($businessId)
                    where([
                        "tag_id" =>  $tag_selected->tag_id
                    ])
                    ->whereBetween(
                        'review_value_news.created_at',
                        [$startDateOfMonth, $endDateOfMonth]
                    )
                    ->filterByOverall($is_overall)
                    ->count();
            }

            // Store tag ID for reference
            $data["selected_tags"][$key]["tag_id"] = $tag_selected->tag_id;

            // Count times this tag was selected in the previous week
            $data["selected_tags"][$key]["tag_selected_time_previous_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->filterByOverall($is_overall)
                ->count();

            // Count times this tag was selected in the current week
            $data["selected_tags"][$key]["tag_selected_time_this_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->filterByOverall($is_overall)
                ->count();

            // Count times this tag was selected in the last 30 days (approximate current month)
            $data["selected_tags"][$key]["tag_selected_time_this_month"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->where('review_value_news.created_at', '>', now()->subDays(30)->endOfDay())
                ->filterByOverall($is_overall)
                ->count();
        }



        // Prepare daily guest review data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("user_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_week_guest_review"][$i]["total"] = $customer;
            $data["this_week_guest_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily guest review data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("user_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_month_guest_review"][$i]["total"] = $customer;
            $data["this_month_guest_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }


        // Prepare daily customer review data for the current week (last 7 days, excluding guests)
        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("guest_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_week_customer_review"][$i]["total"] = $customer;
            $data["this_week_customer_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily customer review data for the current month (last 30 days, excluding guests)
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("guest_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_month_customer_review"][$i]["total"] = $customer;
            $data["this_month_customer_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Loop through each month to calculate question counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["question_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["question_count_monthly"][$i]["value"] = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->count();
        }



        // Prepare daily question data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_week_question"][$i]["total"] = $customer;
            $data["this_week_question"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily question data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_month_question"][$i]["total"] = $customer;
            $data["this_month_question"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Loop through each month to calculate tag counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["tag_count"][$i]["month"] = $month;
            $data["monthly_data"]["tag_count"][$i]["value"] = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->count();
        }



        // Prepare daily tag data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_week_tag"][$i]["total"] = $customer;
            $data["this_week_tag"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily tag data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_month_tag"][$i]["total"] = $customer;
            $data["this_month_tag"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // ----------------------------
        // New Reports Enhancement
        // ----------------------------

        // 1️⃣ Review Growth Rate

        // Count previous month reviews
        $previous_month_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->count();

        // Count this month reviews
        $this_month_reviews = $data['this_month_total_reviews'];

        // Calculate monthly review growth rate
        $data['review_growth_rate_month'] = $previous_month_reviews > 0
            ? round((($this_month_reviews - $previous_month_reviews) / $previous_month_reviews) * 100, 2)
            : 0;

        // Count previous week reviews
        $previous_week_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();

        // Count this week reviews
        $this_week_reviews = $data['this_week_total_reviews'];

        // Calculate weekly review growth rate
        $data['review_growth_rate_week'] = $previous_week_reviews > 0
            ? round((($this_week_reviews - $previous_week_reviews) / $previous_week_reviews) * 100, 2)
            : 0;

        // 2️⃣ Review Source Breakdown
        $sources = (clone $review_query)->distinct()->pluck('source');
        $data['review_source_breakdown'] = $sources->map(fn($source) => [
            'source' => $source,
            'total' => (clone $review_query)->where('source', $source)->count()
        ]);

        // 3️⃣ Review Response Time (average in hours)
        $responses = (clone $review_query)->whereNotNull('responded_at')->get();
        $data['average_response_time_hours'] = $responses->count() > 0
            ? round($responses->avg(fn($r) => \Carbon\Carbon::parse($r->responded_at)->diffInHours($r->created_at)), 2)
            : 0;

        // 4️⃣ Review Language Distribution
        $languages = (clone $review_query)->distinct()->pluck('language');
        $data['review_language_distribution'] = $languages->map(fn($lang) => [
            'language' => $lang,
            'total' => (clone $review_query)->where('language', $lang)->count()
        ]);

        // ⭐ Star Rating Enhancements - FIXED to use ReviewValueNew
        // Get review IDs for rating calculations

        // Get all reviews with calculated rating
        $allReviews = (clone $review_query)->get();

        // Get filtered reviews for specific periods
        $todayReviews = (clone $review_query)
            ->whereDate('created_at', now())
            ->get();

        $thisWeekReviews = (clone $review_query)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->get();

        $thisMonthReviews = (clone $review_query)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get();

        // Calculate average ratings from calculated_rating field
        $avg_ratings = [
            'today' => $todayReviews->isNotEmpty() ? round($todayReviews->avg('calculated_rating'), 1) : 0,
            'this_week' => $thisWeekReviews->isNotEmpty() ? round($thisWeekReviews->avg('calculated_rating'), 1) : 0,
            'this_month' => $thisMonthReviews->isNotEmpty() ? round($thisMonthReviews->avg('calculated_rating'), 1) : 0
        ];

        // All ratings for distribution calculation
        $allRatings = $allReviews->pluck('calculated_rating');
        $validAllRatings = $allRatings->filter();

        $data['average_star_rating'] = array_map(fn($r) => round($r, 2), $avg_ratings);

        // Star Rating Distribution from ReviewValueNew
        $total_reviews_count = count($allReviews);
        $starDistribution = [];

        for ($i = 1; $i <= 5; $i++) {
            $count = 0;
            foreach ($validAllRatings as $rating) {
                if (round($rating) == $i) {
                    $count++;
                }
            }
            $starDistribution[$i] = $total_reviews_count ? round(($count / $total_reviews_count) * 100, 2) : 0;
        }

        $data['star_rating_distribution'] = $starDistribution;

        // Star Rating vs Benchmark
        $industry_benchmark_avg = 4.3;
        $data['star_rating_vs_benchmark'] = [
            'this_month_avg' => round($avg_ratings['this_month'], 2),
            'industry_benchmark' => $industry_benchmark_avg,
            'difference' => round($avg_ratings['this_month'] - $industry_benchmark_avg, 2)
        ];

        // Weighted Star Rating - FIXED to use ReviewValueNew ratings
        $weights = ['verified' => 1.5, 'guest' => 1];
        $weighted_sum = 0;
        $total_weight = 0;


        foreach ($allReviews as  $review) {
            $weight = $review->user_id ? $weights['verified'] : $weights['guest'];
            $weighted_sum += $review->calculated_rating * $weight;
            $total_weight += $weight;
        }

        $data['weighted_star_rating'] = $total_weight ? round($weighted_sum / $total_weight, 2) : 0;


        // Low-Rating Alerts - Optimized version
        $thisWeekReviews = (clone $review_query) // Using the same baseQuery from previous optimization
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->get();

        $lastWeekReviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->get();

        // Count low ratings using calculated_rating field
        $low_rating_this_week = $thisWeekReviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) <= 2;
        })->count();

        $low_rating_last_week = $lastWeekReviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) <= 2;
        })->count();

        $low_rating_increase = $low_rating_last_week ?
            round(($low_rating_this_week - $low_rating_last_week) / $low_rating_last_week * 100, 2) : ($low_rating_this_week ? 100 : 0);

        $data['low_rating_alert'] = [
            'this_week_low_ratings' => $low_rating_this_week,
            'last_week_low_ratings' => $low_rating_last_week,
            'increase_percent' => $low_rating_increase,
            'alert' => $low_rating_increase >= 30
        ];

        // 🏷️ Tag Report Enhancements
        $tags_with_reviews = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            filterByOverall($is_overall)
            ->select('review_value_news.review_id', 'review_value_news.tag_id')
            ->get()
            ->groupBy('review_id');


        $tag_co_occurrence = [];
        foreach ($tags_with_reviews as $review_tags) {
            $tag_ids = $review_tags->pluck('tag_id')->toArray();
            foreach ($tag_ids as $tag1) {
                foreach ($tag_ids as $tag2) {
                    if ($tag1 != $tag2) $tag_co_occurrence[$tag1][$tag2] = ($tag_co_occurrence[$tag1][$tag2] ?? 0) + 1;
                }
            }
        }
        $data['tag_co_occurrence'] = $tag_co_occurrence;

        // Calculate impact of each tag on average rating - OPTIMIZED
        $all_tags = Tag::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->filterByOverall($is_overall)
            ->get();

        // If there are no tags, return empty array
        if ($all_tags->isEmpty()) {
            $data['tag_impact_on_ratings'] = [];
        } else {
            // Get tag IDs
            $tagIds = $all_tags->pluck('id')->toArray();

            // Single query to get average rating per tag
            $tagRatings = ReviewValueNew::join('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                // ->whereMeetsThreshold($businessId)
                ->whereIn('review_value_news.tag_id', $tagIds)
                ->filterByOverall($is_overall)
                ->orderBy('review_news.order_no', 'asc')
                ->select([
                    'review_value_news.tag_id',
                    DB::raw('
                COALESCE(
                    ROUND(
                        AVG(
                            (
                                SELECT AVG(DISTINCT s.value)
                                FROM review_value_news rvn2
                                INNER JOIN stars s ON rvn2.star_id = s.id
                                WHERE rvn2.review_id = review_news.id
                            )
                        ),
                        2
                    ),
                    0
                ) as avg_rating
            ')
                ])
                ->groupBy('review_value_news.tag_id')
                ->get()
                ->keyBy('tag_id');

            // Map results
            $data['tag_impact_on_ratings'] = $all_tags->mapWithKeys(function ($tag) use ($tagRatings) {
                $rating = $tagRatings->get($tag->id);
                return [$tag->id => $rating ? (float) $rating->avg_rating : 0];
            })->toArray();
        }

        // ❓ Question Report Enhancements
        $questions = Question::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->filterByOverall($is_overall)
            ->get();

        $total_users = (clone $review_query)->count();

        $data['question_completion_rate'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => [
                'question_text' => $qst->text,
                'completion_rate' => $total_users ? round(
                    ReviewValueNew::
                        // whereMeetsThreshold($businessId)
                        where('question_id', $qst->id)
                        ->filterByOverall($is_overall)
                        ->count() / $total_users * 100,
                    2
                ) : 0
            ]
        ])->toArray();

        $data['average_response_per_question'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where('question_id', $qst->id)
                ->filterByOverall($is_overall)
                ->count()
        ])->toArray();

        $data['response_distribution'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => collect($qst->options ?? [])->mapWithKeys(fn($opt) => [
                $opt => ReviewValueNew::
                    // whereMeetsThreshold($businessId)
                    where('question_id', $qst->id)
                    ->where('answer', $opt)
                    ->filterByOverall($is_overall)
                    ->count()
            ])->toArray()
        ])->toArray();

        // 📊 Dashboard Trends Enhancements
        $total_review_count = (clone $review_query)->count();

        // Calculate average star rating from ReviewValueNew
        $avg_star = $validAllRatings->isNotEmpty() ? round($validAllRatings->avg(), 2) : 0;

        $data['dashboard_trends'] = [
            'engagement_index' => round($total_review_count * $avg_star, 2),
            'performance_vs_target' => round(($total_review_count / 100) * 100, 2),
            'time_of_day_trends' => collect(range(0, 23))
                ->mapWithKeys(function ($h) use ($review_query) {
                    return [$h => (clone $review_query)
                        ->whereRaw('HOUR(created_at) = ?', [$h])
                        ->count()];
                })
                ->toArray(),
            'day_of_week_trends' => collect(range(0, 6))
                ->mapWithKeys(function ($d) use ($review_query) {
                    return [$d => (clone $review_query)
                        ->whereRaw('DAYOFWEEK(created_at) = ?', [$d + 1])
                        ->count()];
                })
                ->toArray(),
        ];

        // 📈 Advanced Insights
        $reviewers = (clone $review_query)->pluck('user_id')->filter();
        $repeat_reviewers_count = $reviewers->countBy()->filter(fn($c) => $c > 1)->count();
        $total_customers = $reviewers->unique()->count();
        $data['advanced_insights']['customer_retention_rate'] = $total_customers ? round($repeat_reviewers_count / $total_customers * 100, 2) : 0;

        $data['advanced_insights']['topic_analysis'] = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            filterByOverall($is_overall)
            ->select('tag_id', DB::raw('count(*) as total'))
            ->groupBy('tag_id')
            ->get()
            ->map(fn($t) => [
                'tag_id' => $t->tag_id,
                'count' => $t->total,
                'tag_name' => Tag::find($t->tag_id)?->name
            ]);

        $data['advanced_insights']['monthly_review_trend'] = (clone $review_query)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        // Response effectiveness - OPTIMIZED
        $reviewsWithReplies = (clone $review_query)
            ->whereNotNull('responded_at')

            ->get();

        $avgRating = $reviewsWithReplies->isNotEmpty()
            ? round($reviewsWithReplies->avg('calculated_rating'), 2)
            : 0;

        $data['advanced_insights']['response_effectiveness'] = [
            'before_reply_avg' => $avgRating,
            'after_reply_avg' => $avgRating,
            'reviews_with_replies_count' => $reviewsWithReplies->count(),
            'reply_rate_percentage' => $allReviews->count() > 0
                ? round(($reviewsWithReplies->count() / $allReviews->count()) * 100, 1)
                : 0
        ];

        return $data;
    }
   






    private function generateDashboardReportV2(Request $request, $is_overall, $startDate, $endDate)
    {
        $businessId = $request->businessId;
        $data = [];
        $now = Carbon::now();
        $numberOfMonths = $startDate->diffInMonths($endDate);

        /* =========================
     | Date Ranges
     ========================= */
        $today = Carbon::today();
        $thisWeek = [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
        $previousWeek = [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()];
        $thisMonthStart = now()->subDays(30)->endOfDay();
        $previousMonthRange = [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()];

        /* =========================
     | BASE REVIEW QUERIES (ONLY ONCE)
     ========================= */
        $baseReviewQuery = ReviewNew::query()
            ->when(
                !$request->user()->hasRole('superadmin'),
                fn($q) => $q->where('review_news.business_id', $businessId)
            )
            ->globalFilters(0, $businessId)
            ->orderBy('review_news.order_no', 'asc')
            ->filterByOverall($is_overall)
            ->select('review_news.*')
            ->withCalculatedRating();

        $guestReviewQuery = (clone $baseReviewQuery)->whereNull('user_id');
        $customerReviewQuery = (clone $baseReviewQuery)->whereNull('guest_id');
        $authenticatedCustomerQuery = (clone $baseReviewQuery)->whereNotNull('user_id');

        /* =========================
     | QUESTIONS & TAGS BASE
     ========================= */
        $questionQuery = Question::when(
            !$request->user()->hasRole('superadmin'),
            fn($q) => $q->where('business_id', $businessId)
        )->filterByOverall($is_overall);

        $tagQuery = Tag::when(
            !$request->user()->hasRole('superadmin'),
            fn($q) => $q->where('business_id', $businessId)
        )->filterByOverall($is_overall);

        /* =========================
     | TODAY / WEEK
     ========================= */
        $data['today_total_reviews'] =
            (clone $baseReviewQuery)->whereDate('created_at', $today)->count();

        $data['this_week_total_reviews'] =
            (clone $baseReviewQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_total_reviews'] =
            (clone $baseReviewQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_guest_review_count'] =
            (clone $guestReviewQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_guest_review_count'] =
            (clone $guestReviewQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_customer_review_count'] =
            (clone $customerReviewQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_customer_review_count'] =
            (clone $customerReviewQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_question_count'] =
            (clone $questionQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_question_count'] =
            (clone $questionQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_tag_count'] =
            (clone $tagQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_tag_count'] =
            (clone $tagQuery)->whereBetween('created_at', $previousWeek)->count();

        /* =========================
     | MONTH (30 / 60 DAYS)
     ========================= */
        $data['this_month_total_reviews'] =
            (clone $baseReviewQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_total_reviews'] =
            (clone $baseReviewQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_guest_review_count'] =
            (clone $guestReviewQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_guest_review_count'] =
            (clone $guestReviewQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_customer_review_count'] =
            (clone $customerReviewQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_customer_review_count'] =
            (clone $customerReviewQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_question_count'] =
            (clone $questionQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_question_count'] =
            (clone $questionQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_tag_count'] =
            (clone $tagQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_tag_count'] =
            (clone $tagQuery)->whereBetween('created_at', $previousMonthRange)->count();

        /* =========================
     | TOTALS (ALL TIME)
     ========================= */
        $data['total_reviews'] = (clone $baseReviewQuery)->count();
        $data['total_guest_review_count'] = (clone $guestReviewQuery)->count();
        $data['total_customer_review_count'] = (clone $customerReviewQuery)->count();
        $data['total_question_count'] = (clone $questionQuery)->count();
        $data['total_tag_count'] = (clone $tagQuery)->count();

        /* =========================
     | MONTHLY CHART DATA
     ========================= */
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $start = $now->copy()->startOfMonth()->subMonths($i);
            $end = $now->copy()->endOfMonth()->subMonths($i);
            $month = $start->format('F');

            $data['monthly_data']['monthly_reviews'][$i] = [
                'month' => $month,
                'value' => (clone $baseReviewQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $data['monthly_data']['guest_review_count_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $guestReviewQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $data['monthly_data']['customer_review_count_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $customerReviewQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $data['monthly_data']['customers_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $authenticatedCustomerQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->distinct()
                    ->count(),
            ];
        }

        return $data;
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/customer-report",
     *      operationId="customerDashboardReport",
     *      tags={"report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get customer report",
     *      description="This method is to get customer report",
     *       @OA\Parameter(
     * name="customer_id",
     * in="query",
     * description="customer_id",
     * required=true,
     * example="0"
     * ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Customer report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */


    public function customerDashboardReport(Request $request)
    {
        // Get reviews with calculated rating in one query
        $reviews = ReviewNew::with("business", "value")
            ->where([
                "user_id" => $request->customer_id
            ])
            ->globalFilters(0, auth()->user()->business->id)
            ->orderBy('order_no', 'asc')
            ->latest()
            ->withCalculatedRating()
            ->take(5)
            ->get();

        $data["last_five_reviews"] = $reviews;

        return response()->json([
            'success' => true,
            'message' => 'Customer report retrieved successfully',
            'data' => $data
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/business-report",
     *      operationId="businessDashboardReport",
     *      tags={"report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business report",
     *      description="This method is to get business report",
     *       @OA\Parameter(
     * name="business_id",
     * in="query",
     * description="business_id",
     * required=true,
     * example="0"
     * ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function businessDashboardReport(Request $request)
    {
        // VALIDATE REQUEST
        $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
        ]);

        $data = Business::with("owner")->where([
            "id" => $request->business_id
        ])->first();

        // CHECK IF BUSINESS EXISTS
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        // SEND RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Business report retrieved successfully',
            'data' => $data
        ], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/dashboard-report/business/get",
     *      operationId="getBusinessReport",
     *      tags={"report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business report",
     *      description="This method is to get business report",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getBusinessReport(Request $request)
    {
        $data = [];

        $data["total_businesses"] = Business::get()->count();


        $data["previous_week_total_businesses"] = Business::whereDate('businesses.created_at', '>=', Carbon::now()->subWeek()->startOfWeek())
            ->whereDate('businesses.created_at', '<=', Carbon::now()->subWeek()->endOfWeek())
            ->get()->count();


        $data["this_week_total_businesses"] = Business::whereDate('businesses.created_at', '>=', Carbon::now()->startOfWeek())
            ->whereDate('businesses.created_at', '<=', Carbon::now()->endOfWeek())



            ->get()->count();
        return response()->json([
            'success' => true,
            'message' => 'Dashboard report retrieved successfully',
            'data' => $data
        ], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/v3.0/dashboard-report",
     *      operationId="getDashboardReportV3",
     *      tags={"report"},
     *          @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="businessId",
     *         required=false,
     *         example="1"
     *      ),
     *          @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date in d-m-Y format",
     *         required=false,
     *         example="01-12-2025"
     *      ),
     *          @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date in d-m-Y format",
     *         required=false,
     *         example="31-12-2025"
     *      ),
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get dashboard report",
     *      description="This method is to get dashboard report with dynamic date range (default: current month)",


     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getDashboardReportV3(Request $request)
    {
        // Parse and validate date inputs
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfMonth();

        $data = [];

        $data['survey'] = $this->generateDashboardReportV2($request, 0, $startDate, $endDate);
        $data['overall'] = $this->generateDashboardReportV2($request, 1, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard report retrieved successfully',
            'data' => $data
        ], 200);
    }



    private function generateDashboardReport(Request $request, $is_overall, $startDate, $endDate)
    {
        // Get the business ID from the request
        $businessId = $request->businessId;
        $data = [];

        // Get the current date and time
        $now = Carbon::now();

        // Calculate the total number of months between start and end dates
        $numberOfMonths = $startDate->diffInMonths($endDate);

        // Get review query for this business and overall flag WITH calculated rating
        $reviewQuery = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("review_news.business_id", $businessId);
        })
            ->globalFilters(0, $businessId)
            ->filterByOverall($is_overall)
            ->select('review_news.*')
            ->withCalculatedRating();

        // Get reviews with calculated rating for the date range
        $reviews = (clone $reviewQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Calculate average rating from calculated_rating field
        $data["average_rating"] = $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;

        // Loop through each month (from current going backwards)
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            // Get the start date of the month (i months ago)
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            // Get the end date of the same month
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            // Format the month name (e.g., January, February)
            $month = $startDateOfMonth->format('F');

            // Store the month name in the data array
            $data["monthly_data"]["monthly_reviews"][$i]["month"] = $month;

            // Count the number of reviews created in that month
            $data["monthly_data"]["monthly_reviews"][$i]["value"] = (clone $reviewQuery)
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->count();
        }

        // Count total reviews created today
        $data["today_total_reviews"] = (clone $reviewQuery)
            ->whereDate('created_at', Carbon::today())
            ->count();

        // Count total reviews created within the last 30 days (approximate current month)
        $data["this_month_total_reviews"] = (clone $reviewQuery)
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->count();

        // Count total reviews from the previous month (between 30 and 60 days ago)
        $data["previous_month_total_reviews"] = (clone $reviewQuery)
            ->whereBetween(
                'created_at',
                [now()->subDays(60)->startOfDay(), now()->subDays(30)]
            )
            ->count();

        // Count total reviews overall (all-time count)
        $data["total_reviews"] = (clone $reviewQuery)->count();

        // Count total reviews from the previous week (last full week)
        $data["previous_week_total_reviews"] = (clone $reviewQuery)
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->count();

        // Count total reviews created in the current week (from Monday to Sunday)
        $data["this_week_total_reviews"] = (clone $reviewQuery)
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        // Get distinct star ratings selected in reviews
        $total_stars_selected = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            filterByOverall($is_overall)
            ->select("review_value_news.star_id")
            ->distinct()
            ->get();

        // Loop through each distinct star selected
        foreach ($total_stars_selected as $key => $star_selected) {
            // Get the star details from the Star table
            $data["selected_stars"][$key]["star"] = Star::where([
                "id" => $star_selected->star_id
            ])
                ->filterByOverall($is_overall)
                ->first();

            // Count total times this star was selected overall
            $data["selected_stars"][$key]["star_selected_time"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "star_id" => $star_selected->star_id
                ])
                ->filterByOverall($is_overall)
                ->count();

            // Loop through each month to get monthly star selection counts
            for ($i = 0; $i <= $numberOfMonths; $i++) {
                // Start and end dates for the month (i months ago)
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
                $month = $startDateOfMonth->format('F');

                // Store the month name
                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["month"] = $month;

                // Count times this star was selected in the given month
                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["value"] = ReviewValueNew::
                    // whereMeetsThreshold($businessId)
                    where([
                        "star_id" => $star_selected->star_id
                    ])
                    ->whereBetween('review_value_news.created_at', [$startDateOfMonth, $endDateOfMonth])
                    ->filterByOverall($is_overall)
                    ->count();
            }

            // Count times this star was selected in the previous week
            $data["selected_stars"][$key]["star_selected_time_previous_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->filterByOverall($is_overall)
                ->count();

            // Count times this star was selected in the current week
            $data["selected_stars"][$key]["star_selected_time_this_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->filterByOverall($is_overall)
                ->count();
        }

        // Get all distinct tags selected in reviews
        $total_tag_selected = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            select("review_value_news.tag_id")
            ->filterByOverall($is_overall)
            ->distinct()
            ->get();

        // Loop through each distinct tag selected
        foreach ($total_tag_selected as $key => $tag_selected) {
            // Get the tag details from the Tag table
            $data["selected_tags"][$key]["tag"] = Tag::where([
                "id" => $tag_selected->tag_id
            ])
                ->filterByOverall($is_overall)
                ->first();

            // Count total times this tag was selected overall
            $data["selected_tags"][$key]["tag_selected_time"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->filterByOverall($is_overall)
                ->count();

            // Loop through each month to get monthly tag selection counts
            for ($i = 0; $i <= $numberOfMonths; $i++) {
                // Start and end dates for the month (i months ago)
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
                $month = $startDateOfMonth->format('F');

                // Store the month name
                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["month"] = $month;

                // Count times this tag was selected in the given month
                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["value"] = ReviewValueNew::
                    // whereMeetsThreshold($businessId)
                    where([
                        "tag_id" =>  $tag_selected->tag_id
                    ])
                    ->whereBetween(
                        'review_value_news.created_at',
                        [$startDateOfMonth, $endDateOfMonth]
                    )
                    ->filterByOverall($is_overall)
                    ->count();
            }

            // Store tag ID for reference
            $data["selected_tags"][$key]["tag_id"] = $tag_selected->tag_id;

            // Count times this tag was selected in the previous week
            $data["selected_tags"][$key]["tag_selected_time_previous_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->filterByOverall($is_overall)
                ->count();

            // Count times this tag was selected in the current week
            $data["selected_tags"][$key]["tag_selected_time_this_week"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->filterByOverall($is_overall)
                ->count();

            // Count times this tag was selected in the last 30 days (approximate current month)
            $data["selected_tags"][$key]["tag_selected_time_this_month"] = ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->where('review_value_news.created_at', '>', now()->subDays(30)->endOfDay())
                ->filterByOverall($is_overall)
                ->count();
        }

        // Loop through each month to store month names for customer monthly data
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["customers_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["customers_monthly"][$i]["value"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("review_news.business_id", $businessId);
            })
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->whereNotNull('user_id')
                ->globalFilters(0, $businessId)
                ->filterByOverall($is_overall)
                ->distinct()
                ->count();
        }

        // Loop through each month to calculate guest review counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["guest_review_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["guest_review_count_monthly"][$i]["value"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("user_id", NULL);
            })
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();
        }

        // Count guest reviews created in the last 30 days (approximate current month)
        $data["this_month_guest_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("user_id", NULL);
        })
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count guest reviews from the previous month (between 30 and 60 days ago)
        $data["previous_month_guest_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("user_id", NULL);
        })
            ->whereBetween('created_at', [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()])
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count guest reviews created in the current week (Monday to Sunday)
        $data["this_week_guest_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("user_id", NULL);
        })
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count guest reviews created in the previous week
        $data["previous_week_guest_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("user_id", NULL);
        })
            ->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count total guest reviews (all-time)
        $data["total_guest_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("user_id", NULL);
        })
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Prepare daily guest review data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("user_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_week_guest_review"][$i]["total"] = $customer;
            $data["this_week_guest_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily guest review data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("user_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_month_guest_review"][$i]["total"] = $customer;
            $data["this_month_guest_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Loop through each month to calculate customer review counts (excluding guests)
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["customer_review_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["customer_review_count_monthly"][$i]["value"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("guest_id", NULL);
            })
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();
        }

        // Count customer reviews for the current month (last 30 days)
        $data["this_month_customer_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->whereNull("guest_id");
        })
            ->whereDate('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count customer reviews for the previous month (between 30 and 60 days ago)
        $data["previous_month_customer_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("guest_id", NULL);
        })
            ->whereBetween('created_at', [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()])
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count customer reviews for the current week
        $data["this_week_customer_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("guest_id", NULL);
        })
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count customer reviews for the previous week
        $data["previous_week_customer_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("guest_id", NULL);
        })
            ->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Count total customer reviews (all-time, excluding guests)
        $data["total_customer_review_count"] = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId)
                ->where("guest_id", NULL);
        })
            ->filterByOverall($is_overall)
            ->globalFilters(0, $businessId)
            ->count();

        // Prepare daily customer review data for the current week (last 7 days, excluding guests)
        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("guest_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_week_customer_review"][$i]["total"] = $customer;
            $data["this_week_customer_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily customer review data for the current month (last 30 days, excluding guests)
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId)
                    ->where("guest_id", NULL);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters(0, $businessId)
                ->count();

            $data["this_month_customer_review"][$i]["total"] = $customer;
            $data["this_month_customer_review"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Loop through each month to calculate question counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["question_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["question_count_monthly"][$i]["value"] = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->count();
        }

        // Count questions created in the last 30 days (approximate current month)
        $data["this_month_question_count"] = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->count();

        // Count questions from the previous month (between 30 and 60 days ago)
        $data["previous_month_question_count"] = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->whereBetween('created_at', [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()])
            ->filterByOverall($is_overall)
            ->count();

        // Count questions created in the current week
        $data["this_week_question_count"] = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->count();

        // Count questions created in the previous week
        $data["previous_week_question_count"] = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->count();

        // Count total questions (all-time)
        $data["total_question_count"] = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->filterByOverall($is_overall)
            ->count();

        // Prepare daily question data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_week_question"][$i]["total"] = $customer;
            $data["this_week_question"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily question data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = Question::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_month_question"][$i]["total"] = $customer;
            $data["this_month_question"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Loop through each month to calculate tag counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["tag_count"][$i]["month"] = $month;
            $data["monthly_data"]["tag_count"][$i]["value"] = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->count();
        }

        // Count tags created in the current month (last 30 days)
        $data["this_month_tag_count"] = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->count();

        // Count tags created in the previous month (between 30 and 60 days ago)
        $data["previous_month_tag_count"] = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->whereBetween('created_at', [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()])
            ->filterByOverall($is_overall)
            ->count();

        // Count tags created in the current week
        $data["this_week_tag_count"] = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->count();

        // Count tags created in the previous week
        $data["previous_week_tag_count"] = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->count();

        // Count total tags (all-time)
        $data["total_tag_count"] = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
            $q->where("business_id", $businessId);
        })
            ->filterByOverall($is_overall)
            ->count();

        // Prepare daily tag data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_week_tag"][$i]["total"] = $customer;
            $data["this_week_tag"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily tag data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = Tag::when(!$request->user()->hasRole("superadmin"), function ($q) use ($businessId) {
                $q->where("business_id", $businessId);
            })
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->count();

            $data["this_month_tag"][$i]["total"] = $customer;
            $data["this_month_tag"][$i]["date"] = date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // ----------------------------
        // New Reports Enhancement
        // ----------------------------

        // 1️⃣ Review Growth Rate
        $review_query = ReviewNew::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->globalFilters(0, $businessId)
            ->orderBy('order_no', 'asc')
            ->filterByOverall($is_overall)
            ->select('review_news.*')
            ->withCalculatedRating();

        // Count previous month reviews
        $previous_month_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->count();

        // Count this month reviews
        $this_month_reviews = $data['this_month_total_reviews'];

        // Calculate monthly review growth rate
        $data['review_growth_rate_month'] = $previous_month_reviews > 0
            ? round((($this_month_reviews - $previous_month_reviews) / $previous_month_reviews) * 100, 2)
            : 0;

        // Count previous week reviews
        $previous_week_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();

        // Count this week reviews
        $this_week_reviews = $data['this_week_total_reviews'] ?? (clone $review_query)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        // Calculate weekly review growth rate
        $data['review_growth_rate_week'] = $previous_week_reviews > 0
            ? round((($this_week_reviews - $previous_week_reviews) / $previous_week_reviews) * 100, 2)
            : 0;

        // 2️⃣ Review Source Breakdown
        $sources = (clone $review_query)->distinct()->pluck('source');
        $data['review_source_breakdown'] = $sources->map(fn($source) => [
            'source' => $source,
            'total' => (clone $review_query)->where('source', $source)->count()
        ]);

        // 3️⃣ Review Response Time (average in hours)
        $responses = (clone $review_query)->whereNotNull('responded_at')->get();
        $data['average_response_time_hours'] = $responses->count() > 0
            ? round($responses->avg(fn($r) => \Carbon\Carbon::parse($r->responded_at)->diffInHours($r->created_at)), 2)
            : 0;

        // 4️⃣ Review Language Distribution
        $languages = (clone $review_query)->distinct()->pluck('language');
        $data['review_language_distribution'] = $languages->map(fn($lang) => [
            'language' => $lang,
            'total' => (clone $review_query)->where('language', $lang)->count()
        ]);

        // ⭐ Star Rating Enhancements - FIXED to use ReviewValueNew
        // Get review IDs for rating calculations

        // Get all reviews with calculated rating
        $allReviews = (clone $review_query)->get();

        // Get filtered reviews for specific periods
        $todayReviews = (clone $review_query)
            ->whereDate('created_at', now())
            ->get();

        $thisWeekReviews = (clone $review_query)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->get();

        $thisMonthReviews = (clone $review_query)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get();

        // Calculate average ratings from calculated_rating field
        $avg_ratings = [
            'today' => $todayReviews->isNotEmpty() ? round($todayReviews->avg('calculated_rating'), 1) : 0,
            'this_week' => $thisWeekReviews->isNotEmpty() ? round($thisWeekReviews->avg('calculated_rating'), 1) : 0,
            'this_month' => $thisMonthReviews->isNotEmpty() ? round($thisMonthReviews->avg('calculated_rating'), 1) : 0
        ];

        // All ratings for distribution calculation
        $allRatings = $allReviews->pluck('calculated_rating');
        $validAllRatings = $allRatings->filter();

        $data['average_star_rating'] = array_map(fn($r) => round($r, 2), $avg_ratings);

        // Star Rating Distribution from ReviewValueNew
        $total_reviews_count = count($allReviews);
        $starDistribution = [];

        for ($i = 1; $i <= 5; $i++) {
            $count = 0;
            foreach ($validAllRatings as $rating) {
                if (round($rating) == $i) {
                    $count++;
                }
            }
            $starDistribution[$i] = $total_reviews_count ? round(($count / $total_reviews_count) * 100, 2) : 0;
        }

        $data['star_rating_distribution'] = $starDistribution;

        // Star Rating vs Benchmark
        $industry_benchmark_avg = 4.3;
        $data['star_rating_vs_benchmark'] = [
            'this_month_avg' => round($avg_ratings['this_month'], 2),
            'industry_benchmark' => $industry_benchmark_avg,
            'difference' => round($avg_ratings['this_month'] - $industry_benchmark_avg, 2)
        ];

        // Weighted Star Rating - FIXED to use ReviewValueNew ratings
        $weights = ['verified' => 1.5, 'guest' => 1];
        $weighted_sum = 0;
        $total_weight = 0;


        foreach ($allReviews as  $review) {
            $weight = $review->user_id ? $weights['verified'] : $weights['guest'];
            $weighted_sum += $review->calculated_rating * $weight;
            $total_weight += $weight;
        }

        $data['weighted_star_rating'] = $total_weight ? round($weighted_sum / $total_weight, 2) : 0;


        // Low-Rating Alerts - Optimized version
        $thisWeekReviews = (clone $review_query) // Using the same baseQuery from previous optimization
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->get();

        $lastWeekReviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->get();

        // Count low ratings using calculated_rating field
        $low_rating_this_week = $thisWeekReviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) <= 2;
        })->count();

        $low_rating_last_week = $lastWeekReviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) <= 2;
        })->count();

        $low_rating_increase = $low_rating_last_week ?
            round(($low_rating_this_week - $low_rating_last_week) / $low_rating_last_week * 100, 2) : ($low_rating_this_week ? 100 : 0);

        $data['low_rating_alert'] = [
            'this_week_low_ratings' => $low_rating_this_week,
            'last_week_low_ratings' => $low_rating_last_week,
            'increase_percent' => $low_rating_increase,
            'alert' => $low_rating_increase >= 30
        ];

        // 🏷️ Tag Report Enhancements
        $tags_with_reviews = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            filterByOverall($is_overall)
            ->select('review_value_news.review_id', 'review_value_news.tag_id')
            ->get()
            ->groupBy('review_id');


        $tag_co_occurrence = [];
        foreach ($tags_with_reviews as $review_tags) {
            $tag_ids = $review_tags->pluck('tag_id')->toArray();
            foreach ($tag_ids as $tag1) {
                foreach ($tag_ids as $tag2) {
                    if ($tag1 != $tag2) $tag_co_occurrence[$tag1][$tag2] = ($tag_co_occurrence[$tag1][$tag2] ?? 0) + 1;
                }
            }
        }
        $data['tag_co_occurrence'] = $tag_co_occurrence;

        // Calculate impact of each tag on average rating - OPTIMIZED
        $all_tags = Tag::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->filterByOverall($is_overall)
            ->get();

        // If there are no tags, return empty array
        if ($all_tags->isEmpty()) {
            $data['tag_impact_on_ratings'] = [];
        } else {
            // Get tag IDs
            $tagIds = $all_tags->pluck('id')->toArray();

            // Single query to get average rating per tag
            $tagRatings = ReviewValueNew::join('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                // ->whereMeetsThreshold($businessId)
                ->whereIn('review_value_news.tag_id', $tagIds)
                ->filterByOverall($is_overall)
                ->orderBy('review_news.order_no', 'asc')
                ->select([
                    'review_value_news.tag_id',
                    DB::raw('
                COALESCE(
                    ROUND(
                        AVG(
                            (
                                SELECT AVG(DISTINCT s.value)
                                FROM review_value_news rvn2
                                INNER JOIN stars s ON rvn2.star_id = s.id
                                WHERE rvn2.review_id = review_news.id
                            )
                        ),
                        2
                    ),
                    0
                ) as avg_rating
            ')
                ])
                ->groupBy('review_value_news.tag_id')
                ->get()
                ->keyBy('tag_id');

            // Map results
            $data['tag_impact_on_ratings'] = $all_tags->mapWithKeys(function ($tag) use ($tagRatings) {
                $rating = $tagRatings->get($tag->id);
                return [$tag->id => $rating ? (float) $rating->avg_rating : 0];
            })->toArray();
        }

        // ❓ Question Report Enhancements
        $questions = Question::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->filterByOverall($is_overall)
            ->get();

        $total_users = (clone $review_query)->count();

        $data['question_completion_rate'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => [
                'question_text' => $qst->text,
                'completion_rate' => $total_users ? round(
                    ReviewValueNew::
                        // whereMeetsThreshold($businessId)
                        where('question_id', $qst->id)
                        ->filterByOverall($is_overall)
                        ->count() / $total_users * 100,
                    2
                ) : 0
            ]
        ])->toArray();

        $data['average_response_per_question'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => ReviewValueNew::
                // whereMeetsThreshold($businessId)
                where('question_id', $qst->id)
                ->filterByOverall($is_overall)
                ->count()
        ])->toArray();

        $data['response_distribution'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => collect($qst->options ?? [])->mapWithKeys(fn($opt) => [
                $opt => ReviewValueNew::
                    // whereMeetsThreshold($businessId)
                    where('question_id', $qst->id)
                    ->where('answer', $opt)
                    ->filterByOverall($is_overall)
                    ->count()
            ])->toArray()
        ])->toArray();

        // 📊 Dashboard Trends Enhancements
        $total_review_count = (clone $review_query)->count();

        // Calculate average star rating from ReviewValueNew
        $avg_star = $validAllRatings->isNotEmpty() ? round($validAllRatings->avg(), 2) : 0;

        $data['dashboard_trends'] = [
            'engagement_index' => round($total_review_count * $avg_star, 2),
            'performance_vs_target' => round(($total_review_count / 100) * 100, 2),
            'time_of_day_trends' => collect(range(0, 23))
                ->mapWithKeys(function ($h) use ($review_query) {
                    return [$h => (clone $review_query)
                        ->whereRaw('HOUR(created_at) = ?', [$h])
                        ->count()];
                })
                ->toArray(),
            'day_of_week_trends' => collect(range(0, 6))
                ->mapWithKeys(function ($d) use ($review_query) {
                    return [$d => (clone $review_query)
                        ->whereRaw('DAYOFWEEK(created_at) = ?', [$d + 1])
                        ->count()];
                })
                ->toArray(),
        ];

        // 📈 Advanced Insights
        $reviewers = (clone $review_query)->pluck('user_id')->filter();
        $repeat_reviewers_count = $reviewers->countBy()->filter(fn($c) => $c > 1)->count();
        $total_customers = $reviewers->unique()->count();
        $data['advanced_insights']['customer_retention_rate'] = $total_customers ? round($repeat_reviewers_count / $total_customers * 100, 2) : 0;

        $data['advanced_insights']['topic_analysis'] = ReviewValueNew::
            // whereMeetsThreshold($businessId)
            filterByOverall($is_overall)
            ->select('tag_id', DB::raw('count(*) as total'))
            ->groupBy('tag_id')
            ->get()
            ->map(fn($t) => [
                'tag_id' => $t->tag_id,
                'count' => $t->total,
                'tag_name' => Tag::find($t->tag_id)?->name
            ]);

        $data['advanced_insights']['monthly_review_trend'] = (clone $review_query)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        // Response effectiveness - OPTIMIZED
        $reviewsWithReplies = (clone $review_query)
            ->whereNotNull('responded_at')

            ->get();

        $avgRating = $reviewsWithReplies->isNotEmpty()
            ? round($reviewsWithReplies->avg('calculated_rating'), 2)
            : 0;

        $data['advanced_insights']['response_effectiveness'] = [
            'before_reply_avg' => $avgRating,
            'after_reply_avg' => $avgRating,
            'reviews_with_replies_count' => $reviewsWithReplies->count(),
            'reply_rate_percentage' => $allReviews->count() > 0
                ? round(($reviewsWithReplies->count() / $allReviews->count()) * 100, 1)
                : 0
        ];

        return $data;
    }



}
