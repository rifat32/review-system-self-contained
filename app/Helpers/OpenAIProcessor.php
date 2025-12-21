<?php

namespace App\Helpers;

use App\Models\ReviewNew;
use App\Models\User;
use App\Models\BusinessArea;
use App\Models\BusinessService;
use App\Models\OpenAITokenUsage;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenAIProcessor
{
    /**
     * Debug method to check OpenAI status
     */
    public static function debugOpenAIStatus(): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            return ['status' => 'error', 'message' => 'API key not configured'];
        }

        try {
            // Simple test request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Say "Test successful"']
                    ],
                    'max_tokens' => 10
                ]);

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'OpenAI API is working',
                    'status_code' => $response->status()
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'API request failed',
                    'status_code' => $response->status(),
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Connection failed',
                'error' => $e->getMessage()
            ];
        }
    }
    /**
     * Process review with OpenAI
     */
    // In processReviewWithOpenAI method, update caching:

    /**
     * Process review with OpenAI and track token usage
     */
    public static function processReviewWithOpenAI(array $payload): array
    {
        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        try {
            $cacheKey = 'openai_review_' . md5(json_encode($payload));

            // Check cache but only for successful results
            if (Cache::has($cacheKey)) {
                $cached = Cache::get($cacheKey);
                // Only return if not fallback
                if (!isset($cached['_fallback']) || !$cached['_fallback']) {
                    return $cached;
                }
                // If cached result is fallback, continue to get fresh result
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => 1200,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => self::getSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => self::createUserMessage($payload)
                        ]
                    ]
                ]);

            if ($response->failed()) {
                Log::error('OpenAI API failed', [
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            if (empty($content)) {
                throw new \Exception('No content in OpenAI response');
            }

            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON from OpenAI: ' . json_last_error_msg());
            }

            // Extract token usage from response
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;

            // Track token usage BEFORE adding to metadata
            self::trackTokenUsage(
                businessId: $payload['business_id'] ?? null,
                reviewId: $payload['review_id'] ?? null,
                branchId: $payload['metadata']['branch_id'] ?? null,
                model: $model,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                totalTokens: $totalTokens,
                metadata: [
                    'cache_key' => $cacheKey,
                    'cache_hit' => false,
                    'review_text_length' => strlen($payload['review_text'] ?? ''),
                    'has_staff' => !empty($payload['staff_info']),
                    'rating' => $payload['rating'] ?? 0,
                    'source' => $payload['metadata']['source'] ?? 'platform'
                ]
            );

            // Add metadata to result
            $result['_metadata'] = [
                'model' => $model,
                'tokens_used' => $totalTokens,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'processing_time' => now()->toISOString(),
                'business_id' => $payload['business_id'] ?? null,
                'review_id' => $payload['review_id'] ?? null
            ];

            // Only cache successful (non-fallback) results
            if (!isset($result['_fallback'])) {
                Cache::put($cacheKey, $result, 3600); // Cache for 1 hour
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('OpenAI processing failed', [
                'error' => $e->getMessage(),
                'payload' => substr($payload['review_text'] ?? '', 0, 100)
            ]);

            $fallback = self::getFallbackAnalysis($payload);
            $fallback['_error'] = $e->getMessage();

            // Don't cache fallback results
            return $fallback;
        }
    }

      /**
     * Track token usage in database
     */
 /**
 * Track token usage in database
 */
private static function trackTokenUsage(
    ?int $businessId,
    ?int $reviewId,
    ?int $branchId,
    string $model,
    int $promptTokens,
    int $completionTokens,
    int $totalTokens,
    array $metadata = []
): OpenAITokenUsage {
    try {
        // Use your existing calculateCost method or adjust as needed
        $estimatedCost = self::calculateEstimatedCost($model, $promptTokens, $completionTokens);

        return OpenAITokenUsage::create([
            'business_id' => $businessId,
            'review_id' => $reviewId,
            'branch_id' => $branchId,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
            'metadata' => $metadata,
            'created_at' => now()
        ]);
    } catch (\Exception $e) {
        \Log::error('Failed to track token usage', [
            'error' => $e->getMessage(),
            'business_id' => $businessId,
            'review_id' => $reviewId
        ]);
        
        // Return a dummy instance to avoid breaking the flow
        return new OpenAITokenUsage();
    }
}

/**
 * Calculate estimated cost (if not already in your model)
 */
private static function calculateEstimatedCost(string $model, int $promptTokens, int $completionTokens): float
{
    $pricing = [
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-4o' => ['input' => 0.0025, 'output' => 0.010],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
    ];

    $modelPricing = $pricing[$model] ?? $pricing['gpt-4o-mini'];
    
    $inputCost = ($promptTokens / 1000) * $modelPricing['input'];
    $outputCost = ($completionTokens / 1000) * $modelPricing['output'];
    
    return $inputCost + $outputCost;
}

    /**
     * Get system prompt for OpenAI
     */
    private static function getSystemPrompt(): string
    {
        return <<<PROMPT
You are an AI Experience Intelligence Engine. Analyze customer reviews and return ONLY valid JSON in this exact structure:

{
  "language": {
    "detected": "language code",
    "translated_text": "English translation if not English"
  },
  "sentiment": {
    "label": "very_negative|negative|neutral|positive|very_positive",
    "score": -1.0 to 1.0
  },
  "emotion": {
    "primary": "joy|sadness|anger|fear|surprise|disgust|neutral",
    "intensity": "low|medium|high"
  },
  "moderation": {
    "is_abusive": true|false,
    "safe_for_public_display": true|false,
    "issues_found": ["list", "of", "issues"],
    "severity": "low|medium|high"
  },
  "themes": [
    {
      "topic": "topic name",
      "type": "complaint|praise|suggestion",
      "confidence": 0.0 to 1.0
    }
  ],
  "category_analysis": [
    {
      "main_category": "Staff|Service|Food|Ambiance|Cleanliness|Price|Location|Others",
      "sub_category": "specific aspect",
      "sentiment": "negative|neutral|positive",
      "severity": "low|medium|high"
    }
  ],
  "staff_intelligence": {
    "staff_id": "staff id",
    "staff_name": "staff name",
    "mentioned_explicitly": true|false,
    "sentiment_towards_staff": "negative|neutral|positive",
    "soft_skill_scores": {
      "politeness": 1-5,
      "communication": 1-5,
      "empathy": 1-5,
      "professionalism": 1-5
    },
    "training_recommendations": ["list", "of", "trainings"],
    "risk_level": "low|medium|high"
  },
  "service_unit_intelligence": {
    "unit_type": "service unit type",
    "unit_id": "unit id",
    "issues_detected": [],
    "maintenance_required": true|false,
    "performance_score": 1-10
  },
  "business_insights": {
    "root_cause": "main issue identified",
    "repeat_issue_likelihood": "low|medium|high",
    "impact_level": "low|medium|high"
  },
  "recommendations": {
    "business_actions": ["action items"],
    "staff_actions": ["action items"],
    "immediate_actions": ["urgent actions if needed"]
  },
  "alerts": {
    "triggered": true|false,
    "type": "critical|warning|info",
    "priority": "high|medium|low",
    "message": "alert message"
  },
  "explainability": {
    "decision_basis": ["key factors"],
    "confidence_score": 0.0 to 1.0,
    "key_factors": ["important elements"]
  },
  "summary": {
    "one_line": "brief summary",
    "manager_summary": "detailed summary",
    "customer_sentiment_summary": "sentiment summary"
  }
}

ANALYSIS GUIDELINES:

1. LANGUAGE: Detect language, translate to English if needed
2. SENTIMENT: 
   - "terrible", "awful", "worst" = negative/very_negative
   - "excellent", "amazing", "best" = positive/very_positive
   - "average", "okay", "fine" = neutral
3. EMOTION:
   - Angry words = anger
   - Happy words = joy
   - Disappointed words = sadness
   - Fearful words = fear
4. MODERATION: Mark as abusive for hate speech, threats, extreme profanity
5. THEMES: Extract 2-5 key topics mentioned
6. CATEGORY ANALYSIS: Map to business categories
7. STAFF INTELLIGENCE: Analyze staff performance if mentioned
8. RECOMMENDATIONS: Provide actionable, specific recommendations
9. ALERTS: Trigger for safety, legal, or critical issues
10. CONFIDENCE: Based on clarity and detail of review

Be accurate, fair, and business-focused. Return ONLY the JSON object.
PROMPT;
    }

    /**
     * Create user message for OpenAI
     */
    private static function createUserMessage(array $payload): string
    {
        $text = $payload['review_text'] ?? '';
        $rating = $payload['rating'] ?? 0;
        $staffInfo = $payload['staff_info'] ?? null;
        $serviceInfo = $payload['service_info'] ?? null;

        $message = "REVIEW TO ANALYZE:\n";
        $message .= "Text: \"{$text}\"\n";
        $message .= "Rating: {$rating}/5\n";

        if ($staffInfo) {
            $message .= "Staff Mentioned: " . ($staffInfo['staff_name'] ?? 'Unknown') . " (ID: " . ($staffInfo['staff_id'] ?? '') . ")\n";
        }

        if ($serviceInfo) {
            $message .= "Service Area: " . ($serviceInfo['area_name'] ?? '') . "\n";
            if (!empty($serviceInfo['service_name'])) {
                $message .= "Service Type: " . $serviceInfo['service_name'] . "\n";
            }
        }

        $message .= "\nPlease analyze this review comprehensively.";

        return $message;
    }

    /**
     * Create payload from ReviewNew model
     */
    public static function createPayloadFromReview(ReviewNew $review): array
    {
        $text = $review->raw_text ?? $review->comment ?? '';

        // Get staff info
        $staffInfo = null;
        if ($review->staff_id) {
            $staff = User::find($review->staff_id);
            if ($staff) {
                $staffInfo = [
                    'staff_id' => $review->staff_id,
                    'staff_name' => trim($staff->first_Name . ' ' . $staff->last_Name),
                    'job_title' => $staff->job_title ?? '',
                    'department' => $staff->skills ?? ''
                ];
            }
        }

        // Get service/area info
        $serviceInfo = null;
        if ($review->business_area_id) {
            $area = BusinessArea::find($review->business_area_id);
            if ($area) {
                $serviceInfo = [
                    'area_name' => $area->area_name,
                    'area_id' => $area->id
                ];

                if ($area->business_service_id) {
                    $service = BusinessService::find($area->business_service_id);
                    if ($service) {
                        $serviceInfo['service_name'] = $service->name;
                    }
                }
            }
        }

        return [
            'review_text' => $text,
            'rating' => $review->rate ?? 0,
            'staff_info' => $staffInfo,
            'service_info' => $serviceInfo,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'metadata' => [
                'source' => $review->source ?? 'platform',
                'language' => $review->language,
                'review_type' => $review->review_type ?? 'text',
                'is_voice' => $review->is_voice_review ?? false,
                'submitted_at' => $review->responded_at ?? now()->toISOString(),
                'branch_id' => $review->branch_id
            ]
        ];
    }

    /**
     * Analyze a review and save results to database
     */
    // In analyzeReview method, add debugging:

    public static function analyzeReview(ReviewNew $review, bool $forceReprocess = false): array
    {
        // If already processed and not forcing reprocess, return current data
        if ($review->is_ai_processed && !$forceReprocess) {
            return [
                'status' => 'already_processed',
                'sentiment_label' => $review->sentiment_label,
                'sentiment_score' => $review->sentiment_score,
                'emotion' => $review->emotion,
                'ai_confidence' => $review->ai_confidence,
                'is_abusive' => $review->is_abusive,
                'message' => 'Review already processed. Use --force flag to reprocess.'
            ];
        }

        try {
            $payload = self::createPayloadFromReview($review);

            Log::debug('Analyzing review', [
                'review_id' => $review->id,
                'text_length' => strlen($payload['review_text'] ?? ''),
                'has_staff' => !empty($payload['staff_info'])
            ]);

            $openAIResult = self::processReviewWithOpenAI($payload);

            // Check if fallback was used
            if (isset($openAIResult['_fallback']) && $openAIResult['_fallback']) {
                Log::warning('Using fallback analysis for review', [
                    'review_id' => $review->id,
                    'error' => $openAIResult['_error'] ?? 'unknown'
                ]);
            } else {
                Log::info('OpenAI analysis successful for review', [
                    'review_id' => $review->id,
                    'confidence' => $openAIResult['explainability']['confidence_score'] ?? 0
                ]);
            }

            // Convert to database format
            $dbData = self::convertForDatabase($openAIResult, $review);




            // Update the review
            $review->fill($dbData);
            $review->save();

            Log::info('Review analysis completed', [
                'review_id' => $review->id,
                'sentiment' => $dbData['sentiment_label'] ?? 'unknown',
                'confidence' => $dbData['ai_confidence'] ?? 0
            ]);

            return array_merge($dbData, [
                'openai_result' => $openAIResult,
                'status' => 'success',
                'message' => 'Analysis completed successfully',
                "dbDataaaaaaaaaaaaaaaaaa" => $dbData

            ]);
        } catch (\Exception $e) {
            Log::error('Failed to analyze review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return fallback analysis
            $payload = self::createPayloadFromReview($review);
            $fallback = self::getFallbackAnalysis($payload);
            $dbData = self::convertForDatabase($fallback, $review);


            // Update the review
            $review->fill($dbData);
            $review->save();


            return array_merge($dbData, [
                'status' => 'fallback',
                'error' => $e->getMessage(),
                'message' => 'Used fallback analysis due to OpenAI error'
            ]);
        }
    }

    // In OpenAIProcessor class, update the convertForDatabase method:

    private static function convertForDatabase(array $aiResult, ReviewNew $review): array
    {
        // Get sentiment data
        $sentimentScore = $aiResult['sentiment']['score'] ?? 0.0;
        $sentimentLabel = $aiResult['sentiment']['label'] ?? 'neutral';

        // Convert score from -1..1 to 0..1
        $sentimentNormalized = ($sentimentScore + 1) / 2;

        // Get confidence - check multiple possible locations
        $confidence = 0.0;
        if (isset($aiResult['explainability']['confidence_score'])) {
            $confidence = $aiResult['explainability']['confidence_score'];
        } elseif (isset($aiResult['confidence_score'])) {
            $confidence = $aiResult['confidence_score'];
        } elseif (isset($aiResult['_metadata']['confidence'])) {
            $confidence = $aiResult['_metadata']['confidence'];
        } else {
            // Default confidence based on whether we have a proper response
            $confidence = isset($aiResult['_fallback']) ? 0.0 : 0.8; // Default to 80% for OpenAI responses
        }

        // Get emotion
        $emotion = $aiResult['emotion']['primary'] ?? 'neutral';

        // Extract key phrases
        $keyPhrases = [];
        foreach ($aiResult['themes'] ?? [] as $theme) {
            if (!empty($theme['topic'])) {
                $keyPhrases[] = $theme['topic'];
            }
        }

        // Extract topics
        $topics = array_map(function ($theme) {
            return $theme['topic'] ?? '';
        }, $aiResult['themes'] ?? []);

        return [
            'sentiment_score' => $sentimentNormalized,
            'sentiment' => $aiResult['sentiment'],
            'sentiment_label' => $sentimentLabel, // Use OpenAI's label directly
            'emotion' => $emotion,
            'key_phrases' => json_encode(array_slice($keyPhrases, 0, 5)),
            'topics' => json_encode(array_slice($topics, 0, 5)),
            'moderation_results' => json_encode($aiResult['moderation'] ?? []),
            'ai_suggestions' => json_encode($aiResult['recommendations'] ?? []),
            'staff_suggestions' => json_encode($aiResult['staff_intelligence']['training_recommendations'] ?? []),
            'language' => $aiResult['language']['detected'] ?? 'en',
            'openai_raw_response' => json_encode($aiResult),
            'is_ai_processed' => true,
            'is_abusive' => $aiResult['moderation']['is_abusive'] ?? false,
            'ai_confidence' => $confidence,
            'summary' => $aiResult['summary']['one_line'] ?? ''
        ];
    }

    /**
     * Get sentiment label from score - CORRECTED VERSION
     */
    public static function getSentimentLabel(?float $score): string
    {
        if ($score === null) return 'neutral';

        // Ensure score is between 0 and 1
        $score = max(0, min(1, $score));

        // Debug: Check what score we're getting
        if (app()->environment('local')) {
            Log::debug('getSentimentLabel called', ['score' => $score]);
        }

        if ($score >= 0.8) return 'very_positive';
        if ($score >= 0.6) return 'positive';
        if ($score >= 0.4) return 'neutral';
        if ($score >= 0.2) return 'negative';
        return 'very_negative';
    }

    /**
     * Fallback analysis when OpenAI fails - ensure 0 confidence
     */
  private static function getFallbackAnalysis(array $payload): array
    {
        $text = $payload['review_text'] ?? '';
        $rating = $payload['rating'] ?? 0;

        // Simple sentiment analysis based on rating
        if ($rating >= 4) {
            $sentiment = 'positive';
            $sentimentScore = 0.8;
        } elseif ($rating >= 3) {
            $sentiment = 'neutral';
            $sentimentScore = 0.0;
        } else {
            $sentiment = 'negative';
            $sentimentScore = -0.8;
        }

        return [
            'language' => [
                'detected' => 'en',
                'translated_text' => $text
            ],
            'sentiment' => [
                'label' => $sentiment,
                'score' => $sentimentScore
            ],
            'emotion' => [
                'primary' => 'neutral',
                'intensity' => 'low'
            ],
            'moderation' => [
                'is_abusive' => false,
                'safe_for_public_display' => true,
                'issues_found' => [],
                'severity' => 'low'
            ],
            'themes' => [],
            'category_analysis' => [],
            'staff_intelligence' => null,
            'service_unit_intelligence' => null,
            'business_insights' => [
                'root_cause' => 'Unable to analyze',
                'repeat_issue_likelihood' => 'low',
                'impact_level' => 'low'
            ],
            'recommendations' => [
                'business_actions' => [],
                'staff_actions' => [],
                'immediate_actions' => []
            ],
            'alerts' => [
                'triggered' => false,
                'type' => 'info',
                'priority' => 'low',
                'message' => 'Fallback analysis used'
            ],
            'explainability' => [
                'decision_basis' => ['Fallback rating-based analysis'],
                'confidence_score' => 0.0, // Always 0 for fallback
                'key_factors' => ['rating']
            ],
            'summary' => [
                'one_line' => 'Analysis unavailable',
                'manager_summary' => 'Could not perform AI analysis. Using fallback.',
                'customer_sentiment_summary' => 'Based on rating only'
            ],
            '_fallback' => true
        ];
    }



    /**
     * Get sentiment percentage for reports
     */
    public static function getSentimentPercentage($score): int
    {
        if ($score === null) return 50;
        return round($score * 100);
    }

    /**
     * Legacy compatibility method
     */
    public static function processReview(string $text, $staffId = null, $businessId = null): array
    {
        $review = new ReviewNew([
            'raw_text' => $text,
            'staff_id' => $staffId,
            'business_id' => $businessId,
            'comment' => $text
        ]);

        return self::analyzeReview($review);
    }

    /**
     * Batch process reviews
     */
    public static function batchProcessReviews(array $reviewIds): array
    {
        $results = [];
        $reviews = ReviewNew::whereIn('id', $reviewIds)
            ->where('is_ai_processed', 0)
            ->get();

        foreach ($reviews as $review) {
            try {
                $result = self::analyzeReview($review);
                $results[$review->id] = [
                    'success' => true,
                    'sentiment' => $result['sentiment_label'] ?? 'unknown',
                    'emotion' => $result['emotion'] ?? 'unknown'
                ];

                // Rate limiting delay
                usleep(200000); // 200ms

            } catch (\Exception $e) {
                $results[$review->id] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                Log::error('Batch processing failed', [
                    'review_id' => $review->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }
}
