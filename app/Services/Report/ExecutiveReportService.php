<?php

namespace App\Services\Report;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessAiSummary;
use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Review\ReviewMetricsService;
use App\Services\Rule\RuleEngineService;
use App\Services\Rule\RuleReportService;
use App\Services\Staff\StaffPerformanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExecutiveReportService
{
    protected RuleReportService $ruleReportService;
    protected StaffPerformanceService $staffPerformanceService;
    protected ReviewMetricsService $reviewMetricsService;

    public function __construct(
        RuleReportService $ruleReportService,
        StaffPerformanceService $staffPerformanceService,
        ReviewMetricsService $reviewMetricsService
    ) {
        $this->ruleReportService = $ruleReportService;
        $this->staffPerformanceService = $staffPerformanceService;
        $this->reviewMetricsService = $reviewMetricsService;
    }

    /**
     * Get complete Executive Customer Experience Report payload matched 100% with Frontend UI schema & shared metric rules
     */
    public function getExecutiveReport(int $businessId, array $filters = []): array
    {
        $startDate = !empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = !empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        // Base review query scoped by business and filters
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

        if (!empty($filters['sentiment'])) {
            $query->where('sentiment_label', strtolower($filters['sentiment']));
        }

        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn')
                    ->join('q_q_sub_categories as qqsc', 'qqsc.question_id', '=', 'rvn.question_id')
                    ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                    ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                    ->whereColumn('rvn.review_id', 'review_news.id')
                    ->where('qc_parent.id', $filters['category_id']);
            });
        }

        if (!empty($filters['rating'])) {
            $ratingVal = (int) $filters['rating'];
            $query->whereExists(function ($subQuery) use ($ratingVal) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn_r')
                    ->join('stars as s_r', 'rvn_r.star_id', '=', 's_r.id')
                    ->whereColumn('rvn_r.review_id', 'review_news.id')
                    ->groupBy('rvn_r.review_id')
                    ->havingRaw('ROUND(AVG(s_r.value), 1) = ?', [$ratingVal]);
            });
        }

        $reviews = (clone $query)->withCalculatedRating()->get();
        $totalReviews = $reviews->count();
        $avgRating = $this->reviewMetricsService->calculateAverageRating($reviews);

        // Calculate CSAT score using system-wide ReviewMetricsService
        $csatData = $this->reviewMetricsService->calculateCSATScore($businessId, $reviews);
        $csatScore = $csatData['score'];

        // Calculate comparison period metrics for KPIs
        $diffInDays = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = (clone $startDate)->subDays($diffInDays);
        $prevEndDate = (clone $startDate)->subSecond();

        $prevQuery = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate]);

        if (!empty($filters['branch_id'])) {
            $prevQuery->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['survey_id'])) {
            $prevQuery->where('survey_id', $filters['survey_id']);
        }
        if (!empty($filters['source'])) {
            $prevQuery->where('source', $filters['source']);
        }
        if (!empty($filters['sentiment'])) {
            $prevQuery->where('sentiment_label', strtolower($filters['sentiment']));
        }
        if (!empty($filters['staff_id'])) {
            $prevQuery->where('staff_id', $filters['staff_id']);
        }
        if (!empty($filters['category_id'])) {
            $prevQuery->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn')
                    ->join('q_q_sub_categories as qqsc', 'qqsc.question_id', '=', 'rvn.question_id')
                    ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                    ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                    ->whereColumn('rvn.review_id', 'review_news.id')
                    ->where('qc_parent.id', $filters['category_id']);
            });
        }
        if (!empty($filters['rating'])) {
            $ratingVal = (int) $filters['rating'];
            $prevQuery->whereExists(function ($subQuery) use ($ratingVal) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn_r')
                    ->join('stars as s_r', 'rvn_r.star_id', '=', 's_r.id')
                    ->whereColumn('rvn_r.review_id', 'review_news.id')
                    ->groupBy('rvn_r.review_id')
                    ->havingRaw('ROUND(AVG(s_r.value), 1) = ?', [$ratingVal]);
            });
        }

        $prevReviews = $prevQuery->withCalculatedRating()->get();
        $prevTotalVolume = $prevReviews->count();
        $prevAvgRating = $this->reviewMetricsService->calculateAverageRating($prevReviews);

        $csatDiff = round($avgRating - $prevAvgRating, 1);
        $csatDiffStr = ($csatDiff >= 0 ? '+' : '') . $csatDiff;

        $volChangePct = $prevTotalVolume > 0 ? round((($totalReviews - $prevTotalVolume) / $prevTotalVolume) * 100, 1) : 0;
        $volChangeStr = ($volChangePct >= 0 ? '+' : '') . $volChangePct . '%';

        // Use ReviewMetricsService for sentiment calculation
        $sentimentData = $this->reviewMetricsService->calculateSentimentBreakdown($reviews);
        $prevSentimentData = $this->reviewMetricsService->calculateSentimentBreakdown($prevReviews);

        $posCount = $sentimentData['positive'];
        $neuCount = $sentimentData['neutral'];
        $negCount = $sentimentData['negative'];

        $posPct = (int) round($sentimentData['percentages']['positive']);
        $neuPct = (int) round($sentimentData['percentages']['neutral']);
        $negPct = (int) round($sentimentData['percentages']['negative']);

        $netSentiment = $totalReviews > 0 ? round((($posCount - $negCount) / $totalReviews) * 100, 1) : 0;
        $prevPosCount = $prevSentimentData['positive'];
        $prevNegCount = $prevSentimentData['negative'];
        $prevNetSentiment = $prevTotalVolume > 0 ? round((($prevPosCount - $prevNegCount) / $prevTotalVolume) * 100, 1) : 0;

        $netSentimentStr = ($netSentiment >= 0 ? '+' : '') . $netSentiment . '%';
        $netSentimentDiff = round($netSentiment - $prevNetSentiment, 1);
        $netSentimentDiffStr = ($netSentimentDiff >= 0 ? '+' : '') . $netSentimentDiff . '%';

        $lowThreshold = RuleEngineService::getLowRatingThreshold();
        $criticalEscalations = $reviews->filter(fn($r) => ($r->calculated_rating ?? 5.0) <= $lowThreshold)->count();
        $prevCriticalEscalations = $prevReviews->filter(fn($r) => ($r->calculated_rating ?? 5.0) <= $lowThreshold)->count();

        if ($prevCriticalEscalations > 0) {
            $critChangePct = round((($criticalEscalations - $prevCriticalEscalations) / $prevCriticalEscalations) * 100, 1);
            $critChangeStr = ($critChangePct >= 0 ? '+' : '') . $critChangePct . '%';
        } else {
            $critChangeStr = $criticalEscalations > 0 ? '+' . $criticalEscalations : '0%';
        }

        $npsEquivalent = round(($posPct - $negPct) * 0.9);
        $npsStr = ($npsEquivalent >= 0 ? '+' : '') . (int)$npsEquivalent;

        $prevPosPct = (int) round($prevSentimentData['percentages']['positive']);
        $prevNegPct = (int) round($prevSentimentData['percentages']['negative']);
        $prevNpsEquivalent = round(($prevPosPct - $prevNegPct) * 0.9);
        $npsDiff = (int)($npsEquivalent - $prevNpsEquivalent);
        $npsDiffStr = ($npsDiff >= 0 ? '+' : '') . $npsDiff . ' pts';

        $kpis = [
            [
                'label' => 'Overall CSAT Score',
                'value' => number_format($avgRating, 1) . ' / 5.0',
                'change' => $csatDiffStr,
                'isPositive' => $csatDiff >= 0,
                'subtext' => "Satisfaction Index: {$csatScore}%",
            ],
            [
                'label' => 'Total Feedback Volume',
                'value' => number_format($totalReviews),
                'change' => $volChangeStr,
                'isPositive' => $volChangePct >= 0,
                'subtext' => 'Responses collected',
            ],
            [
                'label' => 'Net Sentiment Index',
                'value' => $netSentimentStr,
                'change' => $netSentimentDiffStr,
                'isPositive' => $netSentimentDiff >= 0,
                'subtext' => "{$posPct}% Pos · {$neuPct}% Neu · {$negPct}% Neg",
            ],
            [
                'label' => 'Critical Escalations',
                'value' => (string) $criticalEscalations,
                'change' => $critChangeStr,
                'isPositive' => $criticalEscalations <= $prevCriticalEscalations,
                'subtext' => 'Requires management intervention',
            ],
            [
                'label' => 'NPS Equivalent',
                'value' => $npsStr,
                'change' => $npsDiffStr,
                'isPositive' => $npsDiff >= 0,
                'subtext' => 'Estimated net promoter score',
            ],
        ];

        // 1. Dual-Axis Trend Data (period, rating, volume)
        $trendData = $this->getDualAxisTrendData($businessId, $startDate, $endDate, $filters);

        // 2. Strengths vs Issues (name, strength, issue)
        $strengthsVsIssues = $this->getOperationalStrengthsVsIssues($businessId, $startDate, $endDate, $filters);

        // 3. Top Branches (name, score, volume, status)
        $topBranches = $this->getBranchPerformanceLeaderboard($businessId, $startDate, $endDate);

        // 4. Top Staff (name, role, rating, count)
        $topStaff = $this->getTopFloorStaffLeaderboard($businessId, $startDate, $endDate);

        // 5. AI Summary (badge, title, text)
        $aiSummary = $this->getAiExecutiveBriefing($businessId, $reviews);

        // 6. Key Insights (type, text)
        $keyInsights = $this->getKeyInsights($businessId, $reviews, $posPct, $negCount, $criticalEscalations, $strengthsVsIssues);

        // 7. Recommended Actions (priority, title, desc)
        $recommendedActions = $this->getRecommendedActions($businessId, $reviews, $criticalEscalations, $strengthsVsIssues, $topBranches);

        // 8. Evidence Rows (date, branch, rating, sentiment, comment, summary)
        $evidenceRows = $this->getCustomerEvidenceRows($query, $filters);

        return [
            'title' => 'Executive Customer Experience Report',
            'description' => 'Give business owners and senior management a quick view of overall customer experience performance and the most important things requiring attention.',
            'kpis' => $kpis,
            'trendData' => $trendData,
            'strengthsVsIssues' => $strengthsVsIssues,
            'topBranches' => $topBranches,
            'topStaff' => $topStaff,
            'aiSummary' => $aiSummary,
            'keyInsights' => $keyInsights,
            'recommendedActions' => $recommendedActions,
            'evidenceRows' => $evidenceRows,
            'meta' => [
                'report_title' => 'Executive Customer Experience Report',
                'version' => 'Live V2',
                'period' => [
                    'start_date' => $startDate->toIso8601String(),
                    'end_date' => $endDate->toIso8601String(),
                    'days_count' => $diffInDays,
                ],
                'total_reviews' => $totalReviews,
                'average_rating' => $avgRating,
                'csat_score' => $csatScore,
                'filters_applied' => array_filter($filters),
            ],
        ];
    }

    /**
     * Build monthly/daily time series for dual axis volume & rating chart
     */
    protected function getDualAxisTrendData(int $businessId, Carbon $startDate, Carbon $endDate, array $filters): array
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
        $carbonFormat = $diffDays > 60 ? 'M' : 'M d';

        $data = (clone $query)
            ->join('review_value_news as rvn_trend', 'rvn_trend.review_id', '=', 'review_news.id')
            ->join('stars as s_trend', 'rvn_trend.star_id', '=', 's_trend.id')
            ->select([
                DB::raw("DATE_FORMAT(review_news.created_at, '{$groupByFormat}') as period_key"),
                DB::raw("COUNT(DISTINCT review_news.id) as volume"),
                DB::raw("ROUND(AVG(s_trend.value), 1) as average_rating"),
            ])
            ->groupBy('period_key')
            ->orderBy('period_key', 'asc')
            ->get();

        $formattedPoints = [];
        foreach ($data as $item) {
            $dateObj = Carbon::parse($item->period_key . ($diffDays > 60 ? '-01' : ''));
            $formattedPoints[] = [
                'period' => $dateObj->format($carbonFormat),
                'rating' => (float) $item->average_rating,
                'volume' => (int) $item->volume,
            ];
        }

        return $formattedPoints;
    }

    /**
     * Retrieve AI Executive Briefing summary matching { badge, title, text }
     */
    protected function getAiExecutiveBriefing(int $businessId, $reviews): array
    {
        $aiSummaryRecord = BusinessAiSummary::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->first();

        $total = $reviews->count();
        $avgRating = $this->reviewMetricsService->calculateAverageRating($reviews);

        if ($aiSummaryRecord && !empty($aiSummaryRecord->summary)) {
            return [
                'badge' => 'Executive Briefing',
                'title' => "Performance Summary ({$avgRating}/5.0 Avg Rating across {$total} responses)",
                'text' => $aiSummaryRecord->summary,
            ];
        }

        return [
            'badge' => 'Executive Briefing',
            'title' => $total > 0
                ? "Executive performance overview with {$avgRating} average rating across {$total} responses"
                : "Executive performance overview (No feedback recorded for period)",
            'text' => $total > 0
                ? "Customer sentiment sustained at {$avgRating}/5.0 across {$total} customer feedback entries. Operational metrics indicate active feedback monitoring."
                : "No customer feedback responses recorded in the selected date range and filter criteria.",
        ];
    }

    /**
     * Get Key Insights matching { type, text }
     */
    protected function getKeyInsights(
        int $businessId,
        $reviews,
        int $posPct,
        int $negCount,
        int $criticalEscalations,
        array $strengthsVsIssues
    ): array {
        $total = $reviews->count();
        $topStrength = !empty($strengthsVsIssues) ? $strengthsVsIssues[0] : null;

        $strengthText = $topStrength
            ? "{$topStrength['name']} emerged as top strength with {$topStrength['strength']} positive mentions."
            : "Positive customer sentiment stands at {$posPct}% across collected feedback.";

        $opportunityText = $total > 0
            ? "{$posPct}% of feedback is positive; focus on converting neutral feedback into promoter experience."
            : "Expand survey reach to capture broader customer sentiment across operations.";

        $riskText = $criticalEscalations > 0
            ? "Identified {$criticalEscalations} critical feedback items (<=2 stars) requiring direct operational intervention."
            : ($negCount > 0
                ? "Received {$negCount} negative feedback items requiring management review."
                : "No critical service disruptions reported during this period.");

        return [
            [
                'type' => 'Strength',
                'text' => $strengthText,
            ],
            [
                'type' => 'Opportunity',
                'text' => $opportunityText,
            ],
            [
                'type' => 'Risk Alert',
                'text' => $riskText,
            ],
        ];
    }

    /**
     * Get operational strengths vs issues breakdown matching { name, strength, issue }
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
     * Get Branch Performance Leaderboard matching { name, score, volume, status }
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

    /**
     * Get Top Floor Staff Leaderboard matching { name, role, rating, count }
     */
    protected function getTopFloorStaffLeaderboard(int $businessId, Carbon $startDate, Carbon $endDate): array
    {
        $staffMembers = User::where('business_id', $businessId)
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', [User::businessStaff, User::branchManager]);
            })
            ->get();

        $result = [];
        foreach ($staffMembers as $staff) {
            $staffReviews = ReviewNew::where('staff_id', $staff->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->withCalculatedRating()
                ->get();

            $total = $staffReviews->count();
            $avgRating = $this->reviewMetricsService->calculateAverageRating($staffReviews);

            if ($total > 0) {
                $branchName = $staff->branch && $staff->branch->branch ? $staff->branch->branch->name : 'Staff';
                $result[] = [
                    'name' => trim(($staff->first_Name ?? '') . ' ' . ($staff->last_Name ?? '')) ?: 'Staff #' . $staff->id,
                    'role' => $staff->job_title ?? $branchName,
                    'rating' => (string) number_format($avgRating, 2),
                    'count' => $total,
                ];
            }
        }

        usort($result, fn($a, $b) => (float)$b['rating'] <=> (float)$a['rating']);

        return array_slice($result, 0, 5);
    }

    /**
     * Get customer evidence items matching { date, branch, rating, sentiment, comment, summary }
     */
    protected function getCustomerEvidenceRows($query, array $filters): array
    {
        $reviews = (clone $query)
            ->with(['branch', 'staff'])
            ->withCalculatedRating()
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $items = [];
        foreach ($reviews as $r) {
            $branchName = $r->branch->name ?? 'General Branch';
            $ratingVal = (int) round($r->calculated_rating ?? 5);

            $sentimentLabel = $r->sentiment_label;
            if (!$sentimentLabel && isset($r->sentiment_score)) {
                $sentimentLabel = RuleEngineService::getSentimentLabelFromScore((float)$r->sentiment_score);
            }
            if (!$sentimentLabel) {
                $sentimentLabel = $ratingVal >= 4 ? 'positive' : ($ratingVal <= 2 ? 'negative' : 'neutral');
            }

            $items[] = [
                'date' => $r->created_at->format('Y-m-d'),
                'branch' => $branchName,
                'rating' => $ratingVal,
                'sentiment' => ucfirst($sentimentLabel),
                'comment' => $r->comment ?? $r->description ?? 'No customer comment provided.',
                'summary' => $r->summary ?? "Feedback recorded with rating {$ratingVal}/5",
            ];
        }

        return $items;
    }

    /**
     * Get Recommended Actions list matching { priority, title, desc }
     */
    protected function getRecommendedActions(
        int $businessId,
        $reviews,
        int $criticalEscalations,
        array $strengthsVsIssues,
        array $topBranches
    ): array {
        $actions = [];

        if ($criticalEscalations > 0) {
            $actions[] = [
                'priority' => 'Urgent',
                'title' => 'Address Critical Customer Escalations',
                'desc' => "Review and respond to {$criticalEscalations} low-rating customer feedback entries to mitigate customer churn.",
            ];
        }

        $topIssueCat = null;
        foreach ($strengthsVsIssues as $cat) {
            if ($cat['issue'] > 0 && ($topIssueCat === null || $cat['issue'] > $topIssueCat['issue'])) {
                $topIssueCat = $cat;
            }
        }

        if ($topIssueCat) {
            $actions[] = [
                'priority' => 'High',
                'title' => "Improve Operational Standards in {$topIssueCat['name']}",
                'desc' => "Identified {$topIssueCat['issue']} negative issue mentions in {$topIssueCat['name']}. Conduct targeted operational review.",
            ];
        }

        $needsFocusBranch = null;
        foreach ($topBranches as $b) {
            if (in_array($b['status'], ['Action Required', 'Needs Focus'])) {
                $needsFocusBranch = $b;
                break;
            }
        }

        if ($needsFocusBranch) {
            $actions[] = [
                'priority' => 'High',
                'title' => "Support Branch Performance at {$needsFocusBranch['name']}",
                'desc' => "Branch {$needsFocusBranch['name']} scored {$needsFocusBranch['score']}/5.0. Align with branch manager on service remediation.",
            ];
        } else {
            $actions[] = [
                'priority' => 'Medium',
                'title' => 'Recognize Top Performing Teams',
                'desc' => 'Acknowledge top performing staff and branch locations to encourage ongoing high customer satisfaction.',
            ];
        }

        if (count($actions) < 3) {
            $actions[] = [
                'priority' => 'Medium',
                'title' => 'Standardize Service & Feedback Monitoring',
                'desc' => 'Regularly audit customer feedback categories and staff response velocity to maintain service quality.',
            ];
        }

        return array_slice($actions, 0, 3);
    }
}
