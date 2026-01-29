<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Sentiment Configuration
    |--------------------------------------------------------------------------
    |
    | These settings define how the AI interprets sentiment scores and
    | satisfaction percentages across the entire system.
    |
    */

    'sentiment' => [
        // Mapping of AI sentiment scores (-1.0 to 1.0) to labels
        'score_labels' => [
            ['min' => 0.5, 'max' => 1.0, 'label' => 'positive'],
            ['min' => -0.5, 'max' => 0.49, 'label' => 'neutral'],
            ['min' => -1.0, 'max' => -0.51, 'label' => 'negative'],
        ],

        // Mapping of satisfaction percentages (0 to 100) to labels
        'percentage_labels' => [
            ['min' => 70, 'max' => 100, 'label' => 'Positive'],
            ['min' => 40, 'max' => 69, 'label' => 'Neutral'],
            ['min' => 0, 'max' => 39, 'label' => 'Negative'],
        ],

        // Global sentiment and rating thresholds
        'thresholds' => [
            'positive_score' => 0.7,
            'negative_score' => 0.4,
            'neutral_lower' => 2.1,
            'neutral_upper' => 3.9,
            'csat' => 4.0,
            'default_label' => 'neutral',
            'min_praise_recommendation' => 2,
            'min_mentions_issue' => 2,
            'high_priority_threshold' => 4,
            'min_reviews_top_staff' => 5,
            'trend_threshold' => 0.1,
            'improving_trend_message' => 'Improving sentiment trend',
            'declining_trend_message' => 'Declining sentiment trend',
            'frequent_issue_threshold' => 3,
            'min_reviews_staff_eval' => 3,
            'insufficient_data_message' => 'Insufficient Data',
            'min_reviews_trend' => 4,
            'insufficient_trend_data' => 'insufficient_data',
            'stable_trend_message' => 'stable',
            'summary_template' => "Customers are {{positive}}% positive and {{negative}}% negative.",
            'default_summary_phrase' => "Common themes from recent feedback are analyzed in the insights section.",
            'high_issue_threshold' => 3,
            'min_mentions_recommendation' => 2,
            'min_reviews_staff_analysis' => 3,
        ],

        // Rules for sentiment summary (Numeric status mappings)
        'sentiment_rules' => [
            ['min' => 80, 'max' => 100, 'label' => 'Exceptionally Positive'],
            ['min' => 60, 'max' => 79, 'label' => 'Generally Positive'],
            ['min' => 40, 'max' => 59, 'label' => 'Mixed'],
            ['min' => 20, 'max' => 39, 'label' => 'Mostly Negative'],
            ['min' => 0, 'max' => 19, 'label' => 'Critical'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Topic & Keyword Analysis (Markers for detection)
    |--------------------------------------------------------------------------
    */

    'topics' => [
        // Words to ignore during topic extraction
        'stop_words' => [
            'the',
            'a',
            'an',
            'and',
            'or',
            'but',
            'in',
            'on',
            'at',
            'to',
            'for',
            'of',
            'with',
            'by',
            'from',
            'as',
            'is',
            'was',
            'are',
            'were',
            'be',
            'been',
            'being',
            'have',
            'has',
            'had',
            'do',
            'does',
            'did',
            'will',
            'would',
            'could',
            'should',
            'may',
            'might',
            'can',
            'this',
            'that',
            'these',
            'those',
            'it',
            'its',
            'they',
            'their',
            'them',
            'very',
            'good',
            'bad',
            'great',
            'nice'
        ],

        // Keywords indicating improvement opportunities
        'opportunity_keywords' => [
            'could',
            'should',
            'would',
            'improve',
            'better',
            'suggest',
            'missing',
            'lack'
        ],


    ],

    /*
    |--------------------------------------------------------------------------
    | AI Performance & Predictions
    |--------------------------------------------------------------------------
    */

    'performance' => [
        // Mapping of average ratings to performance labels
        'rating_labels' => [
            ['min' => 4.5, 'max' => 5.0, 'label' => 'Excellent'],
            ['min' => 4.0, 'max' => 4.49, 'label' => 'Very Good'],
            ['min' => 3.5, 'max' => 3.99, 'label' => 'Good'],
            ['min' => 3.0, 'max' => 3.49, 'label' => 'Average'],
            ['min' => 2.0, 'max' => 2.99, 'label' => 'Below Average'],
            ['min' => 0.0, 'max' => 1.99, 'label' => 'Poor'],
        ],

        // Rating prediction rules (Numeric impacts)
        'prediction_rules' => [
            ['min' => 0, 'max' => 1.99, 'impact' => '+0.5 points', 'increase' => 0.5],
            ['min' => 2.0, 'max' => 3.49, 'impact' => '+0.2 points', 'increase' => 0.2],
            ['min' => 3.5, 'max' => 4.49, 'impact' => '+0.1 points', 'increase' => 0.1],
            ['min' => 4.5, 'max' => 5.0, 'impact' => '+0.05 points', 'increase' => 0.05],
        ],

        // Performance levels for staff
        'performance_levels' => [
            ['min' => 4.5, 'max' => 5.0, 'label' => 'Top Performer'],
            ['min' => 3.5, 'max' => 4.49, 'label' => 'Solid Contributor'],
            ['min' => 0.0, 'max' => 3.49, 'label' => 'Needs Improvement'],
        ],

        'performance_categories' => ['Service Speed', 'Product Knowledge', 'Customer Empathy', 'Attention to Detail'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Training Mappings
    |--------------------------------------------------------------------------
    */

    'training' => [


        // Staff evaluations
        'staff_evaluations' => [
            ['min' => 4.0, 'max' => 5.0, 'label' => 'Exceeding Expectations'],
            ['min' => 3.0, 'max' => 3.99, 'label' => 'Meeting Expectations'],
            ['min' => 0.0, 'max' => 2.99, 'label' => 'Below Expectations'],
        ],
    ],
];
