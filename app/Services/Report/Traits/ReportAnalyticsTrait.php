<?php

namespace App\Services\Report\Traits;

use App\Models\Branch;
use App\Models\ReviewNew;
use App\Services\Rule\RuleEngineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait ReportAnalyticsTrait
{
    /**
     * Compute stacked feedback volume by channel grouped by period key
     */
    protected function getStackedFeedbackVolumeByChannel(int $businessId, Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['survey_id'])) {
            $query->where('survey_id', $filters['survey_id']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        $diffDays = $startDate->diffInDays($endDate);
        $groupByFormat = $diffDays > 60 ? '%Y-%m' : '%Y-%m-%d';
        $carbonFormat = $diffDays > 60 ? 'M' : 'D';

        $data = (clone $query)
            ->select([
                DB::raw("DATE_FORMAT(created_at, '{$groupByFormat}') as period_key"),
                'source',
                DB::raw("COUNT(id) as volume"),
            ])
            ->groupBy('period_key', 'source')
            ->orderBy('period_key', 'asc')
            ->get();

        $channels = $data->pluck('source')->filter()->unique()->values()->toArray();
        if (empty($channels)) {
            $channels = ['QR Code', 'Email', 'SMS', 'Google Reviews', 'Voice'];
        }

        $periodsMap = [];
        foreach ($data as $item) {
            $periodKey = $item->period_key;
            $source = $item->source ?? 'Other';
            if (!isset($periodsMap[$periodKey])) {
                $dateObj = Carbon::parse($periodKey . ($diffDays > 60 ? '-01' : ''));
                $periodsMap[$periodKey] = [
                    'period' => $dateObj->format($carbonFormat),
                    'total' => 0,
                    'channels' => [],
                ];
            }
            $periodsMap[$periodKey]['channels'][$source] = (int) $item->volume;
            $periodsMap[$periodKey]['total'] += (int) $item->volume;
        }

        $dayOrder = [
            1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'
        ];

        $daySourceCounts = (clone $query)
            ->select([
                DB::raw("DAYOFWEEK(created_at) as day_num"),
                'source',
                DB::raw("COUNT(id) as volume"),
            ])
            ->groupBy('day_num', 'source')
            ->get();

        $volumeBySourceMap = [];
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d) {
            $volumeBySourceMap[$d] = [
                'day' => $d,
                'QR Code' => 0,
                'Email' => 0,
                'SMS' => 0,
                'Google Reviews' => 0,
                'Voice' => 0,
            ];
        }

        foreach ($daySourceCounts as $row) {
            $dayName = $dayOrder[$row->day_num] ?? 'Mon';
            $src = $row->source ?? 'QR Code';
            if (isset($volumeBySourceMap[$dayName])) {
                $volumeBySourceMap[$dayName][$src] = ($volumeBySourceMap[$dayName][$src] ?? 0) + (int)$row->volume;
            }
        }

        return [
            'available_channels' => $channels,
            'series' => array_values($periodsMap),
            'volumeBySource' => array_values($volumeBySourceMap),
        ];
    }

    /**
     * Compute 1 Star to 5 Stars distribution percentages & counts dynamically
     */
    protected function getRatingDistribution($reviews): array
    {
        $total = $reviews->count();
        $counts = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];

        foreach ($reviews as $review) {
            $star = (int) round($review->calculated_rating ?? 5);
            if ($star < 1) $star = 1;
            if ($star > 5) $star = 5;
            $counts[$star]++;
        }

        $colors = [
            5 => '#10B981',
            4 => '#3B82F6',
            3 => '#F59E0B',
            2 => '#F97316',
            1 => '#EF4444',
        ];

        $distribution = [];
        $ratingDistribution = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $count = $counts[$star];
            $pct = $total > 0 ? (int) round(($count / $total) * 100) : 0;
            $label = "{$star} Star" . ($star > 1 ? 's' : '');
            $distribution[] = [
                'star' => $star,
                'label' => $label,
                'count' => $count,
                'percentage' => $pct,
            ];
            $ratingDistribution[] = [
                'name' => $label,
                'value' => $count,
                'color' => $colors[$star],
            ];
        }

        return [
            'total' => $total,
            'breakdown' => $distribution,
            'ratingDistribution' => $ratingDistribution,
        ];
    }

    /**
     * Compute Feedback Volume by Branch
     */
    protected function getFeedbackVolumeByBranch(int $businessId, Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        $branchesData = (clone $query)
            ->join('branches', 'branches.id', '=', 'review_news.branch_id')
            ->select([
                'branches.name as branch_name',
                DB::raw('COUNT(review_news.id) as volume'),
            ])
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('volume', 'desc')
            ->get();

        $result = [];
        foreach ($branchesData as $b) {
            $result[] = [
                'branch' => $b->branch_name,
                'volume' => (int) $b->volume,
            ];
        }

        return $result;
    }

    /**
     * Compute Operational Strengths vs Issues
     */
    protected function getOperationalStrengthsVsIssues(int $businessId, Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $highRatingThreshold = RuleEngineService::getHighRatingThreshold();
        $lowRatingThreshold = RuleEngineService::getLowRatingThreshold();

        $categoriesData = DB::table('question_categories as qc_parent')
            ->join('question_categories as qc_sub', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
            ->join('q_q_sub_categories as qqsc', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
            ->join('review_value_news as rvn', 'rvn.question_id', '=', 'qqsc.question_id')
            ->join('review_news as r', 'r.id', '=', 'rvn.review_id')
            ->join('stars as s', 's.id', '=', 'rvn.star_id')
            ->where('r.business_id', $businessId)
            ->whereBetween('r.created_at', [$startDate, $endDate])
            ->when(!empty($filters['branch_id']), fn($q) => $q->where('r.branch_id', $filters['branch_id']))
            ->when(!empty($filters['survey_id']), fn($q) => $q->where('r.survey_id', $filters['survey_id']))
            ->when(!empty($filters['source']), fn($q) => $q->where('r.source', $filters['source']))
            ->select([
                'qc_parent.title as name',
                DB::raw("SUM(CASE WHEN s.value >= {$highRatingThreshold} THEN 1 ELSE 0 END) as strength"),
                DB::raw("SUM(CASE WHEN s.value <= {$lowRatingThreshold} THEN 1 ELSE 0 END) as issue"),
            ])
            ->groupBy('qc_parent.id', 'qc_parent.title')
            ->orderByRaw("SUM(CASE WHEN s.value >= {$highRatingThreshold} THEN 1 ELSE 0 END) DESC")
            ->take(5)
            ->get();

        $formatted = [];
        foreach ($categoriesData as $cat) {
            $formatted[] = [
                'name' => $cat->name,
                'strength' => (int) $cat->strength,
                'issue' => (int) $cat->issue,
            ];
        }

        return $formatted;
    }

    /**
     * Compute Branch Performance Leaderboard
     */
    protected function getBranchPerformanceLeaderboard(int $businessId, Carbon $startDate, Carbon $endDate): array
    {
        $branches = Branch::where('business_id', $businessId)->get();

        $result = [];
        foreach ($branches as $branch) {
            $branchReviews = ReviewNew::where('branch_id', $branch->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->withCalculatedRating()
                ->get();

            $total = $branchReviews->count();
            $avgRating = $this->reviewMetricsService->calculateAverageRating($branchReviews);

            if ($total === 0) {
                $status = 'No Activity';
            } elseif ($avgRating < 3.0) {
                $status = 'Action Required';
            } elseif ($avgRating < 4.0) {
                $status = 'Needs Focus';
            } elseif ($total > 30) {
                $status = 'High Growth';
            } else {
                $status = 'Top Performer';
            }

            $result[] = [
                'name' => $branch->name,
                'score' => (string) number_format($avgRating, 1),
                'volume' => number_format($total),
                'status' => $status,
            ];
        }

        usort($result, fn($a, $b) => (float)$b['score'] <=> (float)$a['score']);

        return array_slice($result, 0, 5);
    }
}
