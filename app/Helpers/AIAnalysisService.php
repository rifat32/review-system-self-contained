<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class AIAnalysisService
{
    /**
     * Process review using OpenAI in a single API call
     */
    public static function analyzeWithOpenAI(array $reviewData, array $businessSettings = [])
    {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            Log::error('OpenAI API key not configured');
            return self::generateFallbackResponse($reviewData);
        }
        
        try {
            // Check cache first
            $cacheKey = 'ai_review_' . md5(json_encode($reviewData) . json_encode($businessSettings));
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // Prepare system prompt with business rules
            $systemPrompt = self::buildSystemPrompt($businessSettings);
            
            // Prepare user message with structured data
            $userMessage = self::buildUserMessage($reviewData, $businessSettings);
            
            // Make API call
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.2,
                'max_tokens' => 1200,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($userMessage)
                    ]
                ]
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['choices'][0]['message']['content'])) {
                    $result = json_decode($data['choices'][0]['message']['content'], true);
                    
                    // Validate and enhance the response
                    $validatedResult = self::validateAndEnhanceResponse($result, $reviewData);
                    
                    // Cache the result for 24 hours
                    Cache::put($cacheKey, $validatedResult, 86400);
                    
                    return $validatedResult;
                }
            }
            
            Log::warning('OpenAI API returned unexpected format', ['response' => $response->json() ?? []]);
            return self::generateFallbackResponse($reviewData);
            
        } catch (\Exception $e) {
            Log::error('OpenAI API failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::generateFallbackResponse($reviewData);
        }
    }
    
    /**
     * Build comprehensive system prompt
     */
    private static function buildSystemPrompt(array $businessSettings): string
    {
        $defaultSettings = [
            'language_translation' => true,
            'sentiment_analysis' => true,
            'emotion_detection' => true,
            'abuse_detection' => true,
            'category_analysis' => true,
            'staff_intelligence' => true,
            'service_unit_intelligence' => true,
            'business_recommendations' => true,
            'alerts' => true,
            'explainability' => true,
            'ignore_abusive_reviews_for_staff' => true,
            'min_reviews_for_staff_score' => 3,
            'confidence_threshold' => 0.7
        ];
        
        $settings = array_merge($defaultSettings, $businessSettings);
        
        return "You are an AI Experience Intelligence Engine. Analyze reviews fairly and return ONLY valid JSON exactly matching this schema:
        
{
  \"language\": {
    \"detected\": \"\",
    \"translated_text\": \"\"
  },
  \"sentiment\": {
    \"label\": \"\",
    \"score\": 0.0
  },
  \"emotion\": {
    \"primary\": \"\",
    \"intensity\": \"\"
  },
  \"moderation\": {
    \"is_abusive\": false,
    \"safe_for_public_display\": true,
    \"abuse_type\": \"\",
    \"severity\": \"\"
  },
  \"themes\": [],
  \"category_analysis\": [],
  \"staff_intelligence\": {
    \"staff_id\": \"\",
    \"staff_name\": \"\",
    \"mentioned_explicitly\": false,
    \"sentiment_towards_staff\": \"\",
    \"soft_skill_scores\": {},
    \"training_recommendations\": [],
    \"risk_level\": \"\"
  },
  \"service_unit_intelligence\": {
    \"unit_type\": \"\",
    \"unit_id\": \"\",
    \"issues_detected\": [],
    \"maintenance_required\": false,
    \"performance_rating\": 0.0
  },
  \"business_insights\": {
    \"root_cause\": \"\",
    \"repeat_issue_likelihood\": \"\",
    \"priority_level\": \"\"
  },
  \"recommendations\": {
    \"business_actions\": [],
    \"staff_actions\": []
  },
  \"alerts\": {
    \"triggered\": false,
    \"alert_type\": \"\",
    \"priority\": \"\"
  },
  \"explainability\": {
    \"decision_basis\": [],
    \"confidence_score\": 0.0,
    \"rule_applied\": []
  },
  \"summary\": {
    \"one_line\": \"\",
    \"manager_summary\": \"\"
  }
}

BUSINESS RULES TO FOLLOW:
1. Language Translation: " . ($settings['language_translation'] ? 'Translate non-English reviews to English.' : 'Detect language only.') . "
2. Staff Intelligence: " . ($settings['staff_intelligence'] ? 'Enable staff analysis.' : 'Disable staff analysis.') . "
3. Ignore Abusive Reviews for Staff: " . ($settings['ignore_abusive_reviews_for_staff'] ? 'Yes' : 'No') . "
4. Min Reviews for Staff Score: {$settings['min_reviews_for_staff_score']}
5. Confidence Threshold: {$settings['confidence_threshold']}

ANALYSIS GUIDELINES:
- For sentiment: Use labels 'positive', 'neutral', 'negative'. Score range: -1.0 to 1.0
- For emotion: Primary emotions are 'anger', 'joy', 'sadness', 'fear', 'surprise', 'disgust', 'neutral'
- For abuse detection: Mark as abusive only for hate speech, threats, or extreme profanity
- For themes: Extract key topics mentioned in review
- For category analysis: Map to provided ratings categories
- For staff intelligence: Only analyze if staff is explicitly mentioned
- For service units: Identify any issues with specific service units
- Generate actionable recommendations
- Set alerts for critical issues (safety, legal, repeated complaints)
- Provide clear explainability for each decision

ALWAYS return valid JSON. Do NOT add extra fields. Do not shorten or summarize.";
    }
    
    /**
     * Build structured user message
     */
    private static function buildUserMessage(array $reviewData, array $businessSettings): array
    {
        $defaultSettings = [
            'business_type' => 'generic',
            'branch_id' => null,
            'source' => 'web'
        ];
        
        $settings = array_merge($defaultSettings, $businessSettings);
        
        $message = [
            'business_ai_settings' => [
                'language_translation' => $businessSettings['language_translation'] ?? true,
                'staff_intelligence' => $businessSettings['staff_intelligence'] ?? true,
                'ignore_abusive_reviews_for_staff' => $businessSettings['ignore_abusive_reviews_for_staff'] ?? true,
                'min_reviews_for_staff_score' => $businessSettings['min_reviews_for_staff_score'] ?? 3,
                'confidence_threshold' => $businessSettings['confidence_threshold'] ?? 0.7
            ],
            'review_metadata' => [
                'source' => $reviewData['source'] ?? $settings['source'],
                'business_type' => $reviewData['business_type'] ?? $settings['business_type'],
                'branch_id' => $reviewData['branch_id'] ?? $settings['branch_id'],
                'submitted_at' => $reviewData['submitted_at'] ?? now()->toISOString()
            ],
            'review_content' => [
                'text' => $reviewData['text'] ?? '',
                'voice_review' => $reviewData['voice_review'] ?? false,
                'original_language' => $reviewData['original_language'] ?? null
            ]
        ];
        
        // Add ratings if provided
        if (isset($reviewData['ratings'])) {
            $message['ratings'] = $reviewData['ratings'];
        }
        
        // Add staff context if provided
        if (isset($reviewData['staff_context'])) {
            $message['staff_context'] = $reviewData['staff_context'];
        }
        
        // Add service unit if provided
        if (isset($reviewData['service_unit'])) {
            $message['service_unit'] = $reviewData['service_unit'];
        }
        
        // Add historical context if available
        if (isset($reviewData['historical_data'])) {
            $message['historical_context'] = $reviewData['historical_data'];
        }
        
        return $message;
    }
    
    /**
     * Validate and enhance OpenAI response
     */
    private static function validateAndEnhanceResponse(array $aiResponse, array $reviewData): array
    {
        $defaultResponse = self::getDefaultResponseStructure();
        
        // Merge AI response with defaults
        $result = array_merge($defaultResponse, $aiResponse);
        
        // Ensure required fields exist
        $result['language']['detected'] = $result['language']['detected'] ?? self::detectLanguageFallback($reviewData['text'] ?? '');
        $result['language']['translated_text'] = $result['language']['translated_text'] ?? ($reviewData['text'] ?? '');
        
        // Calculate confidence if not provided
        if ($result['explainability']['confidence_score'] <= 0) {
            $result['explainability']['confidence_score'] = self::calculateConfidenceScore($result);
        }
        
        // Generate summary if not provided
        if (empty($result['summary']['one_line'])) {
            $result['summary'] = self::generateSummaryFallback($reviewData, $result);
        }
        
        // Add metadata
        $result['metadata'] = [
            'processed_at' => now()->toISOString(),
            'analysis_version' => '2.0',
            'engine' => 'openai-gpt-4o-mini'
        ];
        
        return $result;
    }
    
    /**
     * Get default response structure
     */
    private static function getDefaultResponseStructure(): array
    {
        return [
            'language' => [
                'detected' => '',
                'translated_text' => ''
            ],
            'sentiment' => [
                'label' => 'neutral',
                'score' => 0.0
            ],
            'emotion' => [
                'primary' => 'neutral',
                'intensity' => 'medium'
            ],
            'moderation' => [
                'is_abusive' => false,
                'safe_for_public_display' => true,
                'abuse_type' => '',
                'severity' => 'none'
            ],
            'themes' => [],
            'category_analysis' => [],
            'staff_intelligence' => [
                'staff_id' => '',
                'staff_name' => '',
                'mentioned_explicitly' => false,
                'sentiment_towards_staff' => 'neutral',
                'soft_skill_scores' => [],
                'training_recommendations' => [],
                'risk_level' => 'low'
            ],
            'service_unit_intelligence' => [
                'unit_type' => '',
                'unit_id' => '',
                'issues_detected' => [],
                'maintenance_required' => false,
                'performance_rating' => 0.0
            ],
            'business_insights' => [
                'root_cause' => '',
                'repeat_issue_likelihood' => 'low',
                'priority_level' => 'low'
            ],
            'recommendations' => [
                'business_actions' => [],
                'staff_actions' => []
            ],
            'alerts' => [
                'triggered' => false,
                'alert_type' => '',
                'priority' => 'low'
            ],
            'explainability' => [
                'decision_basis' => [],
                'confidence_score' => 0.0,
                'rule_applied' => []
            ],
            'summary' => [
                'one_line' => '',
                'manager_summary' => ''
            ]
        ];
    }
    
    /**
     * Fallback language detection
     */
    private static function detectLanguageFallback(string $text): string
    {
        $commonLanguages = [
            'en' => ['the', 'and', 'for', 'with', 'this'],
            'ur' => ['میں', 'کے', 'ہے', 'نے', 'کی'],
            'ar' => ['في', 'من', 'على', 'أن', 'لا'],
            'es' => ['el', 'la', 'los', 'las', 'de'],
            'fr' => ['le', 'la', 'les', 'de', 'un']
        ];
        
        $textLower = strtolower($text);
        $scores = [];
        
        foreach ($commonLanguages as $lang => $words) {
            $score = 0;
            foreach ($words as $word) {
                if (strpos($textLower, $word) !== false) {
                    $score++;
                }
            }
            $scores[$lang] = $score;
        }
        
        arsort($scores);
        return key($scores) ?: 'en';
    }
    
    /**
     * Calculate confidence score
     */
    private static function calculateConfidenceScore(array $result): float
    {
        $confidence = 0.5; // Base confidence
        
        // Increase based on data completeness
        if (!empty($result['themes'])) $confidence += 0.1;
        if (!empty($result['category_analysis'])) $confidence += 0.1;
        if (!empty($result['recommendations']['business_actions'])) $confidence += 0.1;
        if (!empty($result['explainability']['decision_basis'])) $confidence += 0.1;
        if ($result['sentiment']['score'] != 0.0) $confidence += 0.1;
        
        return min(1.0, max(0.0, $confidence));
    }
    
    /**
     * Generate fallback summary
     */
    private static function generateSummaryFallback(array $reviewData, array $result): array
    {
        $text = $reviewData['text'] ?? '';
        $sentiment = $result['sentiment']['label'] ?? 'neutral';
        
        $oneLine = match($sentiment) {
            'positive' => 'Positive customer feedback received.',
            'negative' => 'Customer reported issues that need attention.',
            default => 'Customer feedback received for review.'
        };
        
        $managerSummary = "Review analysis completed. ";
        
        if (!empty($result['themes'])) {
            $managerSummary .= "Key themes: " . implode(', ', array_column($result['themes'], 'topic')) . ". ";
        }
        
        if ($result['alerts']['triggered']) {
            $managerSummary .= "ALERT: {$result['alerts']['alert_type']} detected. ";
        }
        
        if (!empty($result['recommendations']['business_actions'])) {
            $managerSummary .= "Recommended actions available. ";
        }
        
        return [
            'one_line' => $oneLine,
            'manager_summary' => trim($managerSummary)
        ];
    }
    
    /**
     * Generate fallback response when OpenAI fails
     */
    private static function generateFallbackResponse(array $reviewData): array
    {
        $text = $reviewData['text'] ?? '';
        
        // Use existing sentiment analysis as fallback
        $sentimentScore = self::analyzeSentimentImprovedFallback($text);
        $sentimentLabel = self::getSentimentLabel($sentimentScore);
        $emotion = self::detectEmotionImprovedFallback($text);
        $topics = self::extractTopics($text);
        
        $response = self::getDefaultResponseStructure();
        
        // Populate with fallback data
        $response['language']['detected'] = self::detectLanguageFallback($text);
        $response['language']['translated_text'] = $text;
        $response['sentiment']['label'] = $sentimentLabel;
        $response['sentiment']['score'] = $sentimentScore;
        $response['emotion']['primary'] = $emotion;
        $response['emotion']['intensity'] = $sentimentScore < 0.4 ? 'high' : ($sentimentScore > 0.7 ? 'high' : 'medium');
        $response['themes'] = array_map(function($topic) {
            return ['topic' => $topic, 'type' => 'detected', 'confidence' => 0.6];
        }, $topics);
        
        // Add staff analysis if staff context exists
        if (isset($reviewData['staff_context'])) {
            $response['staff_intelligence']['staff_id'] = $reviewData['staff_context']['staff_id'] ?? '';
            $response['staff_intelligence']['staff_name'] = $reviewData['staff_context']['staff_name'] ?? '';
            $response['staff_intelligence']['mentioned_explicitly'] = strpos($text, $reviewData['staff_context']['staff_name'] ?? '') !== false;
            $response['staff_intelligence']['sentiment_towards_staff'] = $sentimentLabel;
            
            if ($sentimentScore < 0.4) {
                $response['staff_intelligence']['training_recommendations'] = ['Customer service training recommended'];
                $response['staff_intelligence']['risk_level'] = 'medium';
            }
        }
        
        // Add service unit analysis
        if (isset($reviewData['service_unit'])) {
            $response['service_unit_intelligence']['unit_type'] = $reviewData['service_unit']['unit_type'] ?? '';
            $response['service_unit_intelligence']['unit_id'] = $reviewData['service_unit']['unit_id'] ?? '';
        }
        
        // Generate recommendations
        if ($sentimentScore < 0.6) {
            $response['recommendations']['business_actions'] = ['Review customer feedback for improvement opportunities'];
        }
        
        // Explainability
        $response['explainability']['decision_basis'] = ['Fallback analysis used', 'Sentiment: ' . $sentimentLabel];
        $response['explainability']['confidence_score'] = 0.5;
        $response['explainability']['rule_applied'] = ['fallback_mode'];
        
        // Summary
        $response['summary'] = self::generateSummaryFallback($reviewData, $response);
        
        $response['metadata'] = [
            'processed_at' => now()->toISOString(),
            'analysis_version' => '2.0',
            'engine' => 'fallback',
            'note' => 'OpenAI API unavailable, using fallback analysis'
        ];
        
        return $response;
    }
    
    /**
     * Legacy methods for fallback support (from your existing code)
     */
    private static function analyzeSentimentImprovedFallback($text)
    {
        // Copy your existing analyzeSentimentImprovedFallback method here
        // ... [Your existing code] ...
    }
    
    private static function getSentimentLabel(?float $score): string
    {
        // Copy your existing getSentimentLabel method here
        // ... [Your existing code] ...
    }
    
    private static function detectEmotionImprovedFallback($text)
    {
        // Copy your existing detectEmotionImprovedFallback method here
        // ... [Your existing code] ...
    }
    
    private static function extractTopics($text)
    {
        // Copy your existing extractTopics method here
        // ... [Your existing code] ...
    }
    
    /**
     * Utility method to process review with backward compatibility
     */
    public static function processReview($text, $staff_id = null, array $additionalData = [])
    {
        $reviewData = array_merge([
            'text' => $text,
            'source' => 'web',
            'business_type' => 'restaurant',
            'staff_context' => $staff_id ? [
                'staff_selected' => true,
                'staff_id' => $staff_id,
                'staff_name' => 'Staff ' . $staff_id
            ] : null
        ], $additionalData);
        
        return self::analyzeWithOpenAI($reviewData);
    }
    
    /**
     * Batch process multiple reviews
     */
    public static function batchAnalyze(array $reviews, array $businessSettings = []): array
    {
        $results = [];
        
        foreach ($reviews as $review) {
            $results[] = self::analyzeWithOpenAI($review, $businessSettings);
            
            // Respect rate limits - sleep between requests
            usleep(100000); // 0.1 second delay
        }
        
        return $results;
    }
    
    /**
     * Generate explainability report for business owners
     */
    public static function generateExplainabilityReport(array $analysisResult): array
    {
        return [
            'confidence_percentage' => round($analysisResult['explainability']['confidence_score'] * 100) . '%',
            'based_on' => $analysisResult['explainability']['decision_basis'],
            'rules_applied' => $analysisResult['explainability']['rule_applied'],
            'analysis_components' => [
                'sentiment_analysis' => $analysisResult['sentiment']['label'] . ' (' . $analysisResult['sentiment']['score'] . ')',
                'emotion_detection' => $analysisResult['emotion']['primary'] . ' (' . $analysisResult['emotion']['intensity'] . ')',
                'abuse_detection' => $analysisResult['moderation']['is_abusive'] ? 'Flagged' : 'Clean',
                'staff_analysis' => $analysisResult['staff_intelligence']['mentioned_explicitly'] ? 'Performed' : 'Not applicable',
                'key_themes' => count($analysisResult['themes'])
            ],
            'review_count_considered' => 1, // For single review, can be expanded for aggregated analysis
            'timestamp' => $analysisResult['metadata']['processed_at'] ?? now()->toISOString(),
            'engine_version' => $analysisResult['metadata']['analysis_version'] ?? 'unknown'
        ];
    }
}