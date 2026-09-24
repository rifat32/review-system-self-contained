<?php

namespace App\Services\Report;

use App\Models\BusinessAiSummary;
use App\Models\ReviewNew;
use App\Services\Report\Traits\ReportAnalyticsTrait;
use App\Services\Review\ReviewMetricsService;
use App\Services\Rule\RuleEngineService;
use App\Services\Rule\RuleReportService;
use App\Services\Staff\StaffPerformanceService;
use Carbon\Carbon;

class FeedbackOverviewReportService
{
    use ReportAnalyticsTrait;

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
     * Get complete Feedback Overview Report payload matching Frontend Feedback Overview UI schema
     */
    public function getFeedbackOverviewReport(int $businessId, array $filters = []): array
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
                $subQuery->select(\DB::raw(1))
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
                $subQuery->select(\DB::raw(1))
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
        $csatData = $this->reviewMetricsService->calculateCSATScore($businessId, $reviews);
        $csatScore = $csatData['score'];

        $diffInDays = $startDate->diffInDays($endDate) + 1;

        // Comparison period metrics for KPIs
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

        $prevReviews = $prevQuery->get();
        $prevTotal = $prevReviews->count();

        $volChangePct = $prevTotal > 0 ? round((($totalReviews - $prevTotal) / $prevTotal) * 100, 1) : 0;
        $volChangeStr = ($volChangePct >= 0 ? '+' : '') . $volChangePct . '%';

        // Calculate dynamic word count average & period diff
        $totalWords = 0;
        $commentsCount = 0;
        foreach ($reviews as $r) {
            $txt = trim($r->comment ?? $r->description ?? '');
            if (!empty($txt)) {
                $totalWords += str_word_count($txt);
                $commentsCount++;
            }
        }
        $avgWords = $commentsCount > 0 ? (int) round($totalWords / $commentsCount) : 0;

        $prevTotalWords = 0;
        $prevCommentsCount = 0;
        foreach ($prevReviews as $pr) {
            $ptxt = trim($pr->comment ?? $pr->description ?? '');
            if (!empty($ptxt)) {
                $prevTotalWords += str_word_count($ptxt);
                $prevCommentsCount++;
            }
        }
        $prevAvgWords = $prevCommentsCount > 0 ? (int) round($prevTotalWords / $prevCommentsCount) : 0;
        $wordDiff = $avgWords - $prevAvgWords;
        $wordDiffStr = ($wordDiff >= 0 ? '+' : '') . $wordDiff . ' words';

        // Calculate dynamic source scan rates & period diff
        $qrCount = $reviews->filter(fn($r) => str_contains(strtolower($r->source ?? ''), 'qr'))->count();
        $qrScanRate = $totalReviews > 0 ? round(($qrCount / $totalReviews) * 100, 1) : 0;

        $prevQrCount = $prevReviews->filter(fn($r) => str_contains(strtolower($r->source ?? ''), 'qr'))->count();
        $prevQrScanRate = $prevTotal > 0 ? round(($prevQrCount / $prevTotal) * 100, 1) : 0;
        $qrRateDiff = round($qrScanRate - $prevQrScanRate, 1);
        $qrRateDiffStr = ($qrRateDiff >= 0 ? '+' : '') . $qrRateDiff . '%';

        // Comment completion / response rate KPI
        $completionRate = $totalReviews > 0 ? round(($commentsCount / $totalReviews) * 100, 1) : 0;
        $prevCompletionRate = $prevTotal > 0 ? round(($prevCommentsCount / $prevTotal) * 100, 1) : 0;
        $compRateDiff = round($completionRate - $prevCompletionRate, 1);
        $compRateDiffStr = ($compRateDiff >= 0 ? '+' : '') . $compRateDiff . '%';

        $kpis = [
            [
                'label' => 'Total Feedback',
                'value' => number_format($totalReviews),
                'change' => $volChangeStr,
                'isPositive' => $volChangePct >= 0,
                'subtext' => 'Across all channels',
            ],
            [
                'label' => 'Dine-in QR Scan Rate',
                'value' => $qrScanRate . '%',
                'change' => $qrRateDiffStr,
                'isPositive' => $qrRateDiff >= 0,
                'subtext' => 'Table sticker engagement',
            ],
            [
                'label' => 'Comment Completion Rate',
                'value' => $completionRate . '%',
                'change' => $compRateDiffStr,
                'isPositive' => $compRateDiff >= 0,
                'subtext' => 'Feedback with written text',
            ],
            [
                'label' => 'Avg Feedback Length',
                'value' => $avgWords . ' words',
                'change' => $wordDiffStr,
                'isPositive' => $wordDiff >= 0,
                'subtext' => 'Deeper contextual detail',
            ],
        ];

        // 1. Stacked Feedback Volume by Day & Source Channel
        $stackedChannelData = $this->getStackedFeedbackVolumeByChannel($businessId, $startDate, $endDate, $filters);

        // 2. Rating Distribution (1 Star to 5 Stars percentages and counts)
        $ratingDistribution = $this->getRatingDistribution($reviews);

        // 3. Feedback Volume by Branch
        $branchVolumeData = $this->getFeedbackVolumeByBranch($businessId, $startDate, $endDate, $filters);

        // 4. Operational Strengths vs Issues
        $strengthsVsIssues = $this->getOperationalStrengthsVsIssues($businessId, $startDate, $endDate, $filters);

        // 5. Leaderboards
        $topBranches = $this->getBranchPerformanceLeaderboard($businessId, $startDate, $endDate);

        // 6. AI Summary Briefing
        $aiSummary = $this->getAiBriefing($businessId, $reviews);

        // 7. Key Insights
        $sentimentData = $this->reviewMetricsService->calculateSentimentBreakdown($reviews);
        $posPct = (int) round($sentimentData['percentages']['positive']);
        $negCount = $sentimentData['negative'];
        $lowThreshold = RuleEngineService::getLowRatingThreshold();
        $criticalEscalations = $reviews->filter(fn($r) => ($r->calculated_rating ?? 5.0) <= $lowThreshold)->count();

        $keyInsights = $this->getKeyInsights($businessId, $reviews, $posPct, $negCount, $criticalEscalations, $strengthsVsIssues);

        // 8. Recommended Actions
        $recommendedActions = $this->getRecommendedActions($businessId, $reviews, $criticalEscalations, $strengthsVsIssues, $topBranches);

        // 9. Customer Evidence Rows
        $evidenceRows = $this->getCustomerEvidenceRows($query, $filters);

        return [
            'title' => 'Feedback Overview Report',
            'description' => 'Understand the overall volume and distribution of customer feedback.',
            'kpis' => $kpis,
            'stackedChannelData' => $stackedChannelData,
            'volumeBySource' => $stackedChannelData['volumeBySource'],
            'ratingDistribution' => $ratingDistribution['ratingDistribution'],
            'volumeByBranch' => $branchVolumeData,
            'aiSummary' => $aiSummary,
            'keyInsights' => $keyInsights,
            'recommendedActions' => $recommendedActions,
            'evidenceRows' => $evidenceRows,
            'meta' => [
                'report_title' => 'Feedback Overview Report',
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
     * Retrieve AI Summary briefing dynamically based on channel volume
     */
    protected function getAiBriefing(int $businessId, $reviews): array
    {
        $aiSummaryRecord = BusinessAiSummary::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->first();

        $total = $reviews->count();
        $avgRating = $this->reviewMetricsService->calculateAverageRating($reviews);

        // Calculate dominant feedback source channel dynamically
        $channelCounts = [];
        foreach ($reviews as $r) {
            $src = $r->source ?? 'QR Code';
            $channelCounts[$src] = ($channelCounts[$src] ?? 0) + 1;
        }
        arsort($channelCounts);
        $dominantChannel = !empty($channelCounts) ? array_key_first($channelCounts) : 'QR Code';
        $dominantVolume = !empty($channelCounts) ? current($channelCounts) : 0;
        $dominantPct = $total > 0 ? (int) round(($dominantVolume / $total) * 100) : 0;

        $dynamicTitle = $total > 0
            ? "{$dominantChannel} remains dominant channel with {$dominantPct}% share"
            : "Feedback overview (No feedback recorded for period)";

        if ($aiSummaryRecord && !empty($aiSummaryRecord->summary)) {
            return [
                'badge' => 'Channel Analysis',
                'title' => $dynamicTitle,
                'text' => $aiSummaryRecord->summary,
            ];
        }

        return [
            'badge' => 'Channel Analysis',
            'title' => $dynamicTitle,
            'text' => $total > 0
                ? "Customer sentiment sustained at {$avgRating}/5.0 across {$total} customer feedback entries, led by {$dominantChannel} ({$dominantPct}%)."
                : "No customer feedback responses recorded in the selected date range.",
        ];
    }

    /**
     * Get Key Insights dynamically calculated from actual customer data
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

        // Weekend feedback concentration calculation
        $weekendCount = $reviews->filter(function ($r) {
            $day = $r->created_at->dayOfWeek; // 0 = Sun, 6 = Sat
            return $day === 0 || $day === 6;
        })->count();
        $weekendPct = $total > 0 ? (int) round(($weekendCount / $total) * 100) : 0;

        // Top source volume calculation
        $sourceCounts = [];
        foreach ($reviews as $r) {
            $src = $r->source ?? 'QR Code';
            $sourceCounts[$src] = ($sourceCounts[$src] ?? 0) + 1;
        }
        arsort($sourceCounts);
        $topSource = !empty($sourceCounts) ? array_key_first($sourceCounts) : 'QR Code';
        $topSourcePct = $total > 0 ? (int) round((current($sourceCounts) / $total) * 100) : 0;

        return [
            [
                'type' => 'Volume Surge',
                'text' => $total > 0
                    ? "Weekend volume contributes to {$weekendPct}% of weekly total customer inputs."
                    : "No weekend feedback recorded during this period.",
            ],
            [
                'type' => 'Source Shift',
                'text' => $total > 0
                    ? "{$topSource} is the highest performing feedback channel, generating {$topSourcePct}% of overall volume."
                    : "Feedback volume is evenly distributed across monitored touchpoints.",
            ],
        ];
    }

    /**
     * Recommended Actions dynamically generated from actual feedback operational data
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
                'priority' => 'High Priority',
                'title' => 'Address Critical Escalations',
                'desc' => "Investigate {$criticalEscalations} low-rating customer feedback entries requiring immediate team follow-up.",
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
                'priority' => 'High Priority',
                'title' => "Conduct Targeted Operational Audit on {$topIssueCat['name']}",
                'desc' => "{$topIssueCat['issue']} issue mentions detected under {$topIssueCat['name']}. Review operational protocols.",
            ];
        } else {
            $actions[] = [
                'priority' => 'Medium Priority',
                'title' => 'Audit Channel Sticker & QR Code Placements',
                'desc' => 'Ensure table stickers and digital feedback links are active and clear across location touchpoints.',
            ];
        }

        $actions[] = [
            'priority' => 'Medium Priority',
            'title' => 'Optimize Automated Review Syncing',
            'desc' => 'Maintain active monitoring for peak dining windows to maintain high response rates.',
        ];

        return array_slice($actions, 0, 3);
    }

    /**
     * Customer evidence rows matching Feedback Overview UI
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

            $items[] = [
                'date' => $r->created_at->format('Y-m-d'),
                'source' => $r->source ?? 'QR Code Dine-In',
                'branch' => $branchName,
                'rating' => $ratingVal,
                'comment' => $r->comment ?? $r->description ?? 'No customer comment provided.',
            ];
        }

        return $items;
    }
}
