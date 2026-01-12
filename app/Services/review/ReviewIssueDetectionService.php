<?php

namespace App\Services\Review;

use App\Models\ReviewNew;

/**
 * ReviewIssueDetectionService - Detect and analyze repeated issues in reviews
 * Platform-agnostic service for identifying recurring customer complaints
 */
class ReviewIssueDetectionService
{
    /**
     * Detect repeated issues across reviews with time-based analysis
     * 
     * @param mixed $reviews Collection or array of ReviewNew models
     * @param array $options Configuration options
     * @return array Analysis results with repeated issues
     */
    public static function detectRepeatedIssues($reviews, array $options = []): array
    {
        $minOccurrences = $options['min_occurrences'] ?? 3; // Issue must appear at least 3 times
        $minPercentage = $options['min_percentage'] ?? 5;   // Or in at least 5% of reviews
        $includeTrend = $options['include_trend'] ?? true;  // Include trending direction

        // Extended issue detection patterns
        $issuePatterns = [
            'Long Wait Times' => [
                'keywords' => ['wait', 'waiting', 'queue', 'line', 'slow', 'delay', 'took long', 'minutes', 'hours'],
                'severity' => 'high'
            ],
            'Poor Service' => [
                'keywords' => ['rude', 'unhelpful', 'ignore', 'ignored', 'attitude', 'unprofessional', 'dismissive'],
                'severity' => 'high'
            ],
            'Dirty/Unclean' => [
                'keywords' => ['dirty', 'messy', 'filthy', 'unclean', 'hygiene', 'stain', 'smell', 'smelly'],
                'severity' => 'high'
            ],
            'Overpriced' => [
                'keywords' => ['expensive', 'overpriced', 'pricey', 'not worth', 'too much', 'costly'],
                'severity' => 'medium'
            ],
            'Poor Quality' => [
                'keywords' => ['poor quality', 'low quality', 'bad', 'terrible', 'awful', 'subpar'],
                'severity' => 'high'
            ],
            'Parking Issues' => [
                'keywords' => ['parking', 'no parking', 'difficult to park', 'parking lot', 'can\'t park'],
                'severity' => 'low'
            ],
            'Loud/Noisy' => [
                'keywords' => ['loud', 'noisy', 'too loud', 'noise', 'can\'t hear', 'cannot hear'],
                'severity' => 'medium'
            ],
            'Billing Errors' => [
                'keywords' => ['wrong bill', 'overcharged', 'billing error', 'charged twice', 'incorrect charge'],
                'severity' => 'high'
            ],
            'Cold Food' => [
                'keywords' => ['cold food', 'not hot', 'lukewarm', 'cold meal', 'food cold'],
                'severity' => 'medium'
            ],
            'Staff Shortage' => [
                'keywords' => ['understaffed', 'not enough staff', 'staff shortage', 'need more staff'],
                'severity' => 'high'
            ]
        ];

        $totalReviews = is_countable($reviews) ? count($reviews) : $reviews->count();

        if ($totalReviews === 0) {
            return [
                'total_reviews_analyzed' => 0,
                'total_issues_found' => 0,
                'repeated_issues' => [],
                'summary' => 'No reviews available for analysis.'
            ];
        }

        $issueStats = [];

        foreach ($reviews as $review) {
            if (empty($review->comment))
                continue;

            $comment = strtolower($review->comment);
            $reviewDate = $review->created_at;

            foreach ($issuePatterns as $issueName => $pattern) {
                $matched = false;
                $matchedKeyword = null;

                foreach ($pattern['keywords'] as $keyword) {
                    // Use word boundaries for accurate matching
                    if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $comment)) {
                        $matched = true;
                        $matchedKeyword = $keyword;
                        break;
                    }
                }

                if ($matched) {
                    if (!isset($issueStats[$issueName])) {
                        $issueStats[$issueName] = [
                            'issue' => $issueName,
                            'count' => 0,
                            'severity' => $pattern['severity'],
                            'matched_keywords' => [],
                            'review_dates' => [],
                            'sample_reviews' => []
                        ];
                    }

                    $issueStats[$issueName]['count']++;

                    // Track which keywords matched
                    if (!in_array($matchedKeyword, $issueStats[$issueName]['matched_keywords'])) {
                        $issueStats[$issueName]['matched_keywords'][] = $matchedKeyword;
                    }

                    // Track review dates for trend analysis
                    $issueStats[$issueName]['review_dates'][] = $reviewDate;

                    // Store sample comments (max 3)
                    if (count($issueStats[$issueName]['sample_reviews']) < 3) {
                        $issueStats[$issueName]['sample_reviews'][] = [
                            'id' => $review->id,
                            'comment' => substr($review->comment, 0, 150),
                            'rating' => $review->calculated_rating ?? 0,
                            'date' => $reviewDate->format('Y-m-d')
                        ];
                    }
                }
            }
        }

        // Filter by minimum occurrences/percentage
        $repeatedIssues = [];
        foreach ($issueStats as $issueName => $stats) {
            $percentage = ($stats['count'] / $totalReviews) * 100;

            if ($stats['count'] >= $minOccurrences || $percentage >= $minPercentage) {
                $stats['percentage'] = round($percentage, 1);

                // Calculate trend if requested
                if ($includeTrend && count($stats['review_dates']) >= 2) {
                    $stats['trend'] = self::calculateIssueTrend($stats['review_dates']);
                } else {
                    $stats['trend'] = 'stable';
                }

                // Determine priority based on severity, count, and trend
                $stats['priority'] = self::calculateIssuePriority(
                    $stats['severity'],
                    $stats['count'],
                    $stats['trend']
                );

                // Remove review_dates from output (used only for calculation)
                unset($stats['review_dates']);

                $repeatedIssues[] = $stats;
            }
        }

        // Sort by priority (high first) then by count (most frequent first)
        usort($repeatedIssues, function ($a, $b) {
            $priorityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $aPriority = $priorityOrder[$a['priority']] ?? 0;
            $bPriority = $priorityOrder[$b['priority']] ?? 0;

            if ($aPriority !== $bPriority) {
                return $bPriority <=> $aPriority;
            }
            return $b['count'] <=> $a['count'];
        });

        return [
            'total_reviews_analyzed' => $totalReviews,
            'total_issues_found' => count($repeatedIssues),
            'repeated_issues' => $repeatedIssues,
            'summary' => self::generateIssueSummary($repeatedIssues, $totalReviews)
        ];
    }

    /**
     * Calculate if an issue is trending up, down, or stable
     * 
     * @param array $reviewDates Array of Carbon date objects
     * @return string 'increasing', 'decreasing', or 'stable'
     */
    private static function calculateIssueTrend(array $reviewDates): string
    {
        if (count($reviewDates) < 4) {
            return 'stable';
        }

        // Sort dates chronologically
        usort($reviewDates, function ($a, $b) {
            return $a <=> $b;
        });

        // Split into first half and second half
        $halfPoint = ceil(count($reviewDates) / 2);
        $firstHalf = array_slice($reviewDates, 0, $halfPoint);
        $secondHalf = array_slice($reviewDates, $halfPoint);

        // Compare frequency
        if (count($secondHalf) > count($firstHalf) * 1.3) {
            return 'increasing';
        } elseif (count($secondHalf) < count($firstHalf) * 0.7) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Calculate priority level for an issue
     * 
     * @param string $severity Base severity level
     * @param int $count Number of occurrences
     * @param string $trend Trend direction
     * @return string 'critical', 'high', 'medium', or 'low'
     */
    private static function calculateIssuePriority(string $severity, int $count, string $trend): string
    {
        $priority = $severity;

        // Escalate if highly frequent (>10 occurrences)
        if ($count > 10) {
            if ($priority === 'medium') {
                $priority = 'high';
            }
            if ($priority === 'low') {
                $priority = 'medium';
            }
        }

        // Escalate if trending upward
        if ($trend === 'increasing') {
            if ($priority === 'high') {
                $priority = 'critical';
            }
            if ($priority === 'medium') {
                $priority = 'high';
            }
            if ($priority === 'low') {
                $priority = 'medium';
            }
        }

        return $priority;
    }

    /**
     * Generate human-readable summary of issues
     * 
     * @param array $issues Array of repeated issues
     * @param int $totalReviews Total number of reviews analyzed
     * @return string Summary text
     */
    private static function generateIssueSummary(array $issues, int $totalReviews): string
    {
        if (empty($issues)) {
            return 'No significant repeated issues detected. Overall feedback is positive.';
        }

        $criticalCount = count(array_filter($issues, fn($i) => $i['priority'] === 'critical'));
        $highCount = count(array_filter($issues, fn($i) => $i['priority'] === 'high'));

        $summary = "Found " . count($issues) . " repeated issue(s) across {$totalReviews} reviews. ";

        if ($criticalCount > 0) {
            $summary .= "{$criticalCount} critical issue(s) require immediate attention. ";
        }

        if ($highCount > 0) {
            $summary .= "{$highCount} high-priority issue(s) need resolution. ";
        }

        // Mention top issue
        if (!empty($issues)) {
            $topIssue = $issues[0];
            $summary .= "Most frequent: {$topIssue['issue']} ({$topIssue['count']} occurrences, {$topIssue['percentage']}%).";
        }

        return $summary;
    }

    /**
     * Get issue recommendations based on detected patterns
     * 
     * @param array $issueAnalysis Result from detectRepeatedIssues
     * @return array Actionable recommendations
     */
    public static function getIssueRecommendations(array $issueAnalysis): array
    {
        $recommendations = [];

        foreach ($issueAnalysis['repeated_issues'] ?? [] as $issue) {
            $recommendation = self::generateRecommendation($issue);
            if ($recommendation) {
                $recommendations[] = $recommendation;
            }
        }

        return $recommendations;
    }

    /**
     * Generate actionable recommendation for a specific issue
     * 
     * @param array $issue Issue data
     * @return array|null Recommendation or null
     */
    private static function generateRecommendation(array $issue): ?array
    {
        $recommendationMap = [
            'Long Wait Times' => [
                'title' => 'Optimize Service Flow',
                'action' => 'Review staffing schedules during peak hours and implement queue management system.',
                'impact' => 'high'
            ],
            'Poor Service' => [
                'title' => 'Service Training Program',
                'action' => 'Conduct customer service training focusing on communication, empathy, and attentiveness.',
                'impact' => 'high'
            ],
            'Dirty/Unclean' => [
                'title' => 'Enhanced Cleaning Protocol',
                'action' => 'Establish regular cleaning schedules with quality checks and staff accountability.',
                'impact' => 'high'
            ],
            'Overpriced' => [
                'title' => 'Value Communication Strategy',
                'action' => 'Review pricing strategy and ensure clear value proposition communication to customers.',
                'impact' => 'medium'
            ],
            'Poor Quality' => [
                'title' => 'Quality Control Implementation',
                'action' => 'Implement stricter quality checks and establish clear preparation standards.',
                'impact' => 'high'
            ],
            'Parking Issues' => [
                'title' => 'Parking Solutions',
                'action' => 'Consider valet service, partner with nearby parking facilities, or improve signage.',
                'impact' => 'low'
            ],
            'Loud/Noisy' => [
                'title' => 'Ambiance Improvement',
                'action' => 'Assess noise levels and implement sound dampening solutions or adjust music volume.',
                'impact' => 'medium'
            ],
            'Billing Errors' => [
                'title' => 'Billing Process Review',
                'action' => 'Review billing procedures, implement double-check system, and train staff.',
                'impact' => 'high'
            ],
            'Cold Food' => [
                'title' => 'Temperature Management',
                'action' => 'Review kitchen-to-table timing, use warming equipment, and train delivery staff.',
                'impact' => 'medium'
            ],
            'Staff Shortage' => [
                'title' => 'Staffing Assessment',
                'action' => 'Analyze peak hours, consider hiring additional staff or redistributing responsibilities.',
                'impact' => 'high'
            ]
        ];

        $template = $recommendationMap[$issue['issue']] ?? null;

        if (!$template) {
            return null;
        }

        return [
            'issue' => $issue['issue'],
            'priority' => $issue['priority'],
            'occurrences' => $issue['count'],
            'percentage' => $issue['percentage'],
            'trend' => $issue['trend'],
            'recommendation' => [
                'title' => $template['title'],
                'action' => $template['action'],
                'expected_impact' => $template['impact']
            ]
        ];
    }
}
