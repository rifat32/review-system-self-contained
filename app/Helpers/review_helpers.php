<?php

if (!function_exists('aiModeration')) {
    /**
     * Step 2: AI Moderation Pipeline
     */
    function aiModeration($text)
    {
        $abusivePatterns = ['idiot', 'stupid', 'hate', 'terrible', 'awful', 'shit', 'fuck', 'asshole'];
        $hateSpeechIndicators = ['racist', 'sexist', 'discriminat', 'bigot'];
        $spamPatterns = ['http://', 'https://', 'www.', 'buy now', 'click here', 'discount', 'offer'];

        $issues = [];
        $severity = 0;

        // Check for abusive words
        foreach ($abusivePatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                $issues[] = 'abusive_language';
                $severity += 1;
            }
        }

        // Check for hate speech
        foreach ($hateSpeechIndicators as $indicator) {
            if (stripos($text, $indicator) !== false) {
                $issues[] = 'hate_speech';
                $severity += 2;
            }
        }

        // Check for spam
        foreach ($spamPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                $issues[] = 'spam_content';
                $severity += 1;
            }
        }

        // Determine action based on severity
        $action = 'allow';
        $shouldBlock = false;
        $actionMessage = 'Content approved';

        if ($severity >= 3) {
            $action = 'block';
            $shouldBlock = true;
            $actionMessage = 'Content blocked due to inappropriate language';
        } elseif ($severity >= 2) {
            $action = 'flag_for_review';
            $actionMessage = 'Content flagged for admin review';
        } elseif ($severity >= 1) {
            $action = 'warn';
            $actionMessage = 'Content contains mild inappropriate language';
        }

        return [
            'issues_found' => $issues,
            'severity_score' => $severity,
            'action_taken' => $action,
            'should_block' => $shouldBlock,
            'action_message' => $actionMessage
        ];
    }
}


if (!function_exists('analyzeSentiment')) {

    function analyzeSentiment($text)
    {
        $api_key = env('HF_API_KEY');

        // Using a simple sentiment analysis model
        $ch = curl_init("https://router.huggingface.co/hf-inference/models/cardiffnlp/twitter-roberta-base-sentiment-latest");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $text]),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);

        // Convert to 0-1 scale
        if (isset($data[0])) {
            $scores = $data[0];
            if (isset($scores['label'])) {
                switch ($scores['label']) {
                    case 'positive':
                        return min(1.0, 0.7 + ($scores['score'] ?? 0) * 0.3);
                    case 'negative':
                        return max(0.0, 0.3 - ($scores['score'] ?? 0) * 0.3);
                    case 'neutral':
                        return 0.5;
                }
            }
        }

        return 0.5; // Default neutral
    }
}

if (!function_exists('extractTopics')) {
    /**
     * Step 4: AI Topic Extraction
     */
    function extractTopics($text)
    {
        $predefinedTopics = [
            'wait time',
            'cleanliness',
            'staff politeness',
            'product quality',
            'price issues',
            'service speed',
            'atmosphere',
            'food quality',
            'customer service',
            'waiting area',
            'billing process'
        ];

        $detectedTopics = [];
        $textLower = strtolower($text);

        foreach ($predefinedTopics as $topic) {
            if (strpos($textLower, strtolower($topic)) !== false) {
                $detectedTopics[] = $topic;
            }
        }

        return $detectedTopics;
    }
}

if (!function_exists('analyzeStaffPerformance')) {

    function analyzeStaffPerformance($text, $staff_id)
    {

        if ($staff_id) {
            $performanceIssues = [
                'communication' => ['understand', 'explain', 'listen', 'communication', 'rude', 'polite'],
                'service_speed' => ['slow', 'fast', 'wait', 'quick', 'delay', 'time'],
                'product_knowledge' => ['know', 'information', 'explain', 'helpful', 'knowledge'],
                'attitude' => ['friendly', 'rude', 'polite', 'nice', 'unprofessional']
            ];

            $textLower = strtolower($text);
            $weaknesses = [];

            foreach ($performanceIssues as $skill => $indicators) {
                $negativeCount = 0;

                foreach ($indicators as $indicator) {
                    if (strpos($textLower, $indicator) !== false) {
                        $negativeCount++;
                    }
                }

                if ($negativeCount > 0) {
                    $weaknesses[] = $skill;
                }
            }

            return generateStaffSuggestions($weaknesses);
        }
        return [];
    }
}


if (!function_exists('analyzeStaffPerformance')) {

    function analyzeStaffPerformance($text, $staff_id)
    {

        if ($staff_id) {
            $performanceIssues = [
                'communication' => ['understand', 'explain', 'listen', 'communication', 'rude', 'polite'],
                'service_speed' => ['slow', 'fast', 'wait', 'quick', 'delay', 'time'],
                'product_knowledge' => ['know', 'information', 'explain', 'helpful', 'knowledge'],
                'attitude' => ['friendly', 'rude', 'polite', 'nice', 'unprofessional']
            ];

            $textLower = strtolower($text);
            $weaknesses = [];

            foreach ($performanceIssues as $skill => $indicators) {
                $negativeCount = 0;

                foreach ($indicators as $indicator) {
                    if (strpos($textLower, $indicator) !== false) {
                        $negativeCount++;
                    }
                }

                if ($negativeCount > 0) {
                    $weaknesses[] = $skill;
                }
            }

            return generateStaffSuggestions($weaknesses);
        }
        return [];
    }
}

function generateStaffSuggestions($weaknesses)
{
    $suggestions = [];

    foreach ($weaknesses as $weakness) {
        switch ($weakness) {
            case 'communication':
                $suggestions[] = 'Needs better communication skills training';
                break;
            case 'service_speed':
                $suggestions[] = 'Requires efficiency and time management training';
                break;
            case 'product_knowledge':
                $suggestions[] = 'Needs product knowledge workshop';
                break;
            case 'attitude':
                $suggestions[] = 'Customer service excellence training recommended';
                break;
        }
    }

    return $suggestions;
}


if (!function_exists('generateRecommendations')) {

    function generateRecommendations($topics, $sentiment_score)
    {
        $recommendations = [];

        if (in_array('wait time', $topics) && $sentiment_score < 0.4) {
            $recommendations[] = 'Consider adding additional staff during peak hours to reduce wait times';
        }

        if (in_array('cleanliness', $topics) && $sentiment_score < 0.4) {
            $recommendations[] = 'Implement more frequent cleaning schedules and staff training';
        }

        if (in_array('staff politeness', $topics) && $sentiment_score < 0.4) {
            $recommendations[] = 'Provide additional customer service training to staff';
        }

        if (in_array('price issues', $topics)) {
            $recommendations[] = 'Review pricing strategy and consider competitive analysis';
        }

        return $recommendations;
    }
}


if (!function_exists('detectEmotion')) {
    function detectEmotion($text)
    {
        $api_key = env('HF_API_KEY');

        $ch = curl_init("https://router.huggingface.co/hf-inference/models/j-hartmann/emotion-english-distilroberta-base");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $text]),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data[0]['label'] ?? null;
    }
}


if (!function_exists('extractKeyPhrases')) {
    function extractKeyPhrases($text)
    {
        $api_key = env('HF_API_KEY');

        $ch = curl_init("https://router.huggingface.co/hf-inference/models/ml6team/keyphrase-extraction-kbir-inspec");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $text]),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data['keyphrases'] ?? [];
    }
}
