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
        // Mapping of AI sentiment scores (0.0 to 1.0) to labels
        'score_labels' => [
            ['min' => 0.7, 'max' => 1.0, 'label' => 'positive'],
            ['min' => 0.4, 'max' => 0.69, 'label' => 'neutral'],
            ['min' => 0.0, 'max' => 0.39, 'label' => 'negative'],
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
            'csat' => 4.0,
            'high_rating' => 4.0,
            'low_rating' => 2.0,
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

        'intensity_mapping' => [
            'high' => 0.9,
            'medium' => 0.6,
            'low' => 0.3,
            'default' => 0.5,
        ],

        'numeric_epsilon' => 0.01,
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
        'rounding_precision' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Processor Configuration
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'request' => [
            'debug_timeout' => 10,
            'process_timeout' => 60,
            'temperature' => 0.1,
            'max_tokens' => 2500,
            'debug_max_tokens' => 10,
            'cache_ttl' => 3600,
            'retry_times' => 3,
            'retry_sleep' => 1000, // milliseconds
        ],

        'anomalies' => [
            'mismatch_high_rating' => 4,
            'mismatch_negative_sentiment' => 0.3,
            'mismatch_low_rating' => 2,
            'mismatch_positive_sentiment' => 0.7,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rule Engine & Insights Configuration
    |--------------------------------------------------------------------------
    */

    'insights' => [
        'aggregation' => [
            'default_days' => 30,
            'min_mentions' => 2,
            'review_id_cap' => 100,
            'dashboard_limit' => 10,
            'query_batch_size' => 50,
        ],

        'confidence' => [
            'thresholds' => [
                'high' => 80,
                'medium' => 60,
            ],
            'mentions_scores' => [
                ['min' => 10, 'score' => 40],
                ['min' => 5, 'score' => 30],
                ['min' => 3, 'score' => 20],
                ['min' => 2, 'score' => 10],
            ],
            'severity_scores' => [
                'low' => 10,
                'medium' => 20,
                'high' => 30,
            ],
            'trend_scores' => [
                'stable' => 5,
                'emerging' => 15,
                'increasing' => 20,
            ],
            'time_factors' => [
                ['max_days' => 7, 'score' => 10],
                ['max_days' => 14, 'score' => 5],
            ],
            'adjustments' => [
                'critical' => 20,
                'high' => 10,
            ],
        ],

        'trends' => [
            'emerging' => ['days' => 7, 'mentions' => 3],
            'increasing' => ['days' => 14, 'mentions' => 5],
        ],

        'opportunities' => [
            'min_rec_length' => 5,
            'dynamic_thresholds' => [
                'mentions' => 3,
                'severity' => 'high',
            ],
            'top_count' => 3,
            'min_staff_mentions' => 3,
            'common_issue_min' => 2,
            'preview' => [
                'sample_limit' => 50,
                'match_display_limit' => 5,
                'base_precision' => 85.0,
                'high_precision_cap' => 98.0,
                'high_confidence_matches' => 10,
                'medium_confidence_matches' => 3,
            ],
            'seeding' => [
                'emotion_intensity' => 0.7,
                'mismatch_rating_high' => 3,
                'mismatch_rating_low' => 2,
            ],
            'reporting' => [
                'mismatch_high_rating' => 4,
                'mismatch_low_rating' => 2,
                'trend_limit_days' => 30,
            ],
            'generation' => [
                'default_days' => 30,
                'min_mentions' => 2,
                'limit' => 5,
            ],
            'dashboard' => [
                'days' => 7,
                'limit' => 3,
            ],
            'priority_weights' => [
                'critical' => 4,
                'high' => 3,
                'medium' => 2,
                'low' => 1,
            ],
        ],

        'severity_escalation' => [
            'very_high_frequency' => 10,
            'high_frequency' => 5,
        ],
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
