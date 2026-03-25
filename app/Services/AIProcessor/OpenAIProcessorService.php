<?php

namespace App\Services\AIProcessor;

use App\Models\BusinessAiModule;
use App\Models\ReviewNew;
use App\Models\User;
use App\Models\OpenAITokenUsage;
use App\Models\Tag;
use App\Services\Rule\RuleEngineService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class OpenAIProcessorService
{
    private RuleEngineService $ruleEngineService;

    public function __construct(RuleEngineService $ruleEngineService)
    {
        $this->ruleEngineService = $ruleEngineService;
    }

    /**
     * Debug method to check OpenAI status
     */
    public function debugOpenAIStatus(): array
    {
        $apiKey = \config('services.openai.api_key');

        if (empty($apiKey)) {
            return ['status' => 'error', 'message' => 'API key not configured'];
        }

        try {
            // Simple test request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(config('ai.openai.request.debug_timeout') ?? 10)
                ->retry(config('ai.openai.request.retry_times') ?? 3, config('ai.openai.request.retry_sleep') ?? 1000)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Say "Test successful"']
                    ],
                    'max_tokens' => config('ai.openai.request.debug_max_tokens') ?? 10
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
     * Get business AI modules with fallback to defaults
     */
    public function getBusinessAiModules(int $business_id): array
    {
        try {
            // Eager load everything needed for the modules check
            $business = \App\Models\Business::with([
                'current_subscription.service_plan.modules',
                'service_plan.modules'
            ])->find($business_id);

            if (!$business) return [];

            // 1. Check if the business is considered subscribed (trial, legacy, or new system)
            if (!$business->is_subscribed) {
                return [];
            }

            $allowedModules = [];

            // 2. Try getting modules from the official subscription record
            if ($business->current_subscription && $business->current_subscription->service_plan) {
                $allowedModules = $business->current_subscription->service_plan->modules->pluck('name')->toArray();
            }
            // 3. Fallback to direct plan ID if subscribed via trial/legacy but no subscription record
            elseif ($business->service_plan) {
                $allowedModules = $business->service_plan->modules->pluck('name')->toArray();
            }

            $modules = \App\Models\Module::where('is_enabled', true)->get();
            $enabledModules = [];

            foreach ($modules as $module) {
                // Feature is enabled only if it's both active in system AND allowed by plan/subscription
                $enabledModules[$module->name] = in_array($module->name, $allowedModules);
            }

            return $enabledModules;
        } catch (\Exception $e) {
            Log::error('Failed to get business AI modules', [
                'business_id' => $business_id,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }


    // In processReviewWithOpenAI method, update caching:

    public function processReviewWithOpenAI(array $payload, array $enabledModules): array
    {
        $apiKey = \config('services.openai.api_key');
        $model = \config('services.openai.model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        try {
            // Include enabled modules in cache key
            $cacheKey = 'openai_review_' . md5(json_encode($payload) . json_encode($enabledModules));

            // Check cache but only for successful results
            if (Cache::has($cacheKey)) {
                $cached = Cache::get($cacheKey);
                // Only return if not fallback
                if (!isset($cached['_fallback']) || !$cached['_fallback']) {
                    return $cached;
                }
            }

            $systemPrompt = $this->getSystemPrompt($enabledModules);
            $userMessage = $this->createUserMessage($payload, $enabledModules);


            $dynamicMaxTokens = config('ai.openai.request.max_tokens') ?? 2500;

            Log::debug('Sending to OpenAI with modules', [
                'enabled_modules' => $enabledModules,
                'text_length' => mb_strlen($payload['review_text'] ?? ''),
                'system_prompt_length' => mb_strlen($systemPrompt),
                'user_message_length' => mb_strlen($userMessage),
                'dynamic_max_tokens' => $dynamicMaxTokens,
                'estimated_completion_tokens' => $this->estimateCompletionTokens($enabledModules, $payload)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(config('ai.openai.request.process_timeout') ?? 60)
                ->retry(config('ai.openai.request.retry_times') ?? 3, config('ai.openai.request.retry_sleep') ?? 1000)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => config('ai.openai.request.temperature') ?? 0.1,
                    'max_tokens' => $dynamicMaxTokens, // Dynamic based on modules
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt
                        ],
                        [
                            'role' => 'user',
                            'content' => $userMessage
                        ]
                    ]
                ]);

            if ($response->failed()) {
                Log::error('OpenAI API failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'headers' => $response->headers()
                ]);
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $data = $response->json();


            // Log the full response structure for debugging
            Log::debug('OpenAI API response structure', [
                'has_choices' => isset($data['choices']),
                'choices_count' => isset($data['choices']) ? count($data['choices']) : 0,
                'has_finish_reason' => isset($data['choices'][0]['finish_reason']),
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
                'has_usage' => isset($data['usage']),
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'max_tokens_used_percentage' => $dynamicMaxTokens > 0 ? round(($data['usage']['completion_tokens'] ?? 0) / $dynamicMaxTokens * 100, 1) : 0
            ]);

            $content = $data['choices'][0]['message']['content'] ?? '';

            if (empty($content)) {
                Log::error('Empty content from OpenAI', [
                    'data_structure' => array_keys($data),
                    'choices_structure' => isset($data['choices']) ? array_keys($data['choices'][0] ?? []) : []
                ]);
                throw new \Exception('No content in OpenAI response');
            }

            // Log the raw content length for debugging
            Log::debug('OpenAI raw content stats', [
                'content_length' => mb_strlen($content),
                'content_preview_begin' => mb_substr($content, 0, 100),
                'content_preview_end' => mb_substr($content, -100),
                'content_has_newlines' => str_contains($content, "\n") ? 'yes' : 'no',
                'content_has_tabs' => str_contains($content, "\t") ? 'yes' : 'no'
            ]);

            // Check if response was truncated
            $finishReason = $data['choices'][0]['finish_reason'] ?? null;
            if ($finishReason === 'length') {
                Log::warning('OpenAI response likely truncated due to token limit', [
                    'finish_reason' => $finishReason,
                    'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                    'max_tokens' => $dynamicMaxTokens,
                    'content_ends_with' => mb_substr($content, -50)
                ]);

                // Try to fix truncated JSON
                $content = $this->fixTruncatedJson($content);
            }

            // Clean the content before JSON parsing
            $cleanedContent = $this->cleanJsonContent($content);

            // Try to parse JSON
            $result = json_decode($cleanedContent, true);

            // Add this validation check:
            if (isset($payload['rating']) && isset($result['sentiment']['score'])) {
                $rating = $payload['rating'];
                $sentimentScore = $result['sentiment']['score'];
                $sentimentLabel = $result['sentiment']['label'] ?? 'unknown';

                $anomalies = config('ai.openai.anomalies', []);

                // Flag severe mismatches (high rating but negative sentiment)
                if ($rating >= ($anomalies['mismatch_high_rating'] ?? 4) && $sentimentScore <= ($anomalies['mismatch_negative_sentiment'] ?? 0.3)) {
                    Log::warning('SEVERE RATING-SENTIMENT MISMATCH', [
                        'review_id' => $payload['review_id'] ?? 'unknown',
                        'rating' => $rating,
                        'sentiment_score' => $sentimentScore,
                        'sentiment_label' => $sentimentLabel,
                        'rating_comment_alignment' => $result['rating_comment_alignment']['is_aligned'] ?? 'unknown',
                        'mismatch_type' => $result['rating_comment_alignment']['mismatch_type'] ?? 'unknown'
                    ]);
                }

                // Also check for other anomalies (low rating but positive sentiment)
                if ($rating <= ($anomalies['mismatch_low_rating'] ?? 2) && $sentimentScore >= ($anomalies['mismatch_positive_sentiment'] ?? 0.7)) {
                    Log::warning('LOW RATING WITH POSITIVE SENTIMENT', [
                        'review_id' => $payload['review_id'] ?? 'unknown',
                        'rating' => $rating,
                        'sentiment_score' => $sentimentScore,
                        'sentiment_label' => $sentimentLabel
                    ]);
                }
            }

            // After parsing the OpenAI result
            Log::debug('OpenAI parsed sentiment', [
                'sentiment_score' => $result['sentiment']['score'] ?? null,
                'sentiment_label' => $result['sentiment']['label'] ?? null,
                'review_preview' => mb_substr($payload['review_text'] ?? '', 0, 50)
            ]);

            // If parsing fails, try with error detection
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON parsing failed initial attempt', [
                    'error' => json_last_error_msg(),
                    'error_code' => json_last_error(),
                    'finish_reason' => $finishReason,
                    'content_sample_start' => mb_substr($cleanedContent, 0, 200),
                    'content_sample_end' => mb_substr($cleanedContent, -200),
                    'content_full_length' => mb_strlen($cleanedContent)
                ]);

                // Try to fix common JSON issues
                $fixedContent = $this->fixCommonJsonIssues($cleanedContent);
                $result = json_decode($fixedContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Last attempt: extract JSON from string
                    $extractedJson = $this->extractJsonFromString($cleanedContent);
                    $result = json_decode($extractedJson, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Log the problematic content
                        $errorLine = $this->findJsonErrorLine($cleanedContent);
                        throw new \Exception('Invalid JSON from OpenAI: ' . json_last_error_msg() .
                            ' (Code: ' . json_last_error() . ') at approximately position: ' . $errorLine .
                            ' (Finish reason: ' . $finishReason . ')');
                    }
                }
            }

            // Validate required fields
            if (!isset($result['sentiment']) || !isset($result['sentiment']['label'])) {
                Log::warning('Missing required fields in OpenAI response', [
                    'has_sentiment' => isset($result['sentiment']),
                    'has_sentiment_label' => isset($result['sentiment']['label']),
                    'result_keys' => array_keys($result)
                ]);
            }

            // Extract token usage
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;

            // Track token usage
            $this->trackTokenUsage(
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
                    'enabled_modules' => $enabledModules,
                    'review_text_length' => mb_strlen($payload['review_text'] ?? ''),
                    'has_staff' => !empty($payload['staff_info']),
                    'rating' => $payload['rating'] ?? 0,
                    'source' => $payload['metadata']['source'] ?? 'web',
                    'response_length' => mb_strlen($content),
                    'finish_reason' => $finishReason,
                    'max_tokens_setting' => $dynamicMaxTokens,
                    'token_usage_percentage' => $dynamicMaxTokens > 0 ? round(($completionTokens / $dynamicMaxTokens) * 100, 1) : 0
                ]
            );

            // Add metadata to result
            $result['_metadata'] = [
                'model' => $model,
                'tokens_used' => $totalTokens,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'enabled_modules' => $enabledModules,
                'processing_time' => \now()->toISOString(),
                'business_id' => $payload['business_id'] ?? null,
                'review_id' => $payload['review_id'] ?? null,
                'finish_reason' => $finishReason,
                'max_tokens_setting' => $dynamicMaxTokens,
                'token_usage_percentage' => $dynamicMaxTokens > 0 ? round(($completionTokens / $dynamicMaxTokens) * 100, 1) : 0
            ];

            // Only cache successful (non-fallback) results
            if (!isset($result['_fallback'])) {
                Cache::put($cacheKey, $result, config('ai.openai.request.cache_ttl') ?? 3600);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('OpenAI processing failed', [
                'error' => $e->getMessage(),
                'error_trace' => mb_substr($e->getTraceAsString(), 0, 500),
                'payload_text' => mb_substr($payload['review_text'] ?? '', 0, 100),
                'payload_length' => mb_strlen($payload['review_text'] ?? ''),
                'business_id' => $payload['business_id'] ?? null,
                'enabled_modules' => $enabledModules
            ]);

            // Re-throw exception to allow caller to handle failure (retry or mark as failed)
            throw $e;
        }
    }




    private function estimateCompletionTokens(array $enabledModules, array $payload): int
    {
        $estimate = 800; // Increased from 500 (base for required modules)

        if ($enabledModules['category_analysis'] ?? false) {
            $estimate += 300;
        }

        if ($enabledModules['staff_intelligence'] ?? false && !empty($payload['staff_info'])) {
            $estimate += 400;
        }

        if ($enabledModules['business_recommendations'] ?? false) {
            $estimate += 500;
        }

        // Longer reviews need more tokens
        $reviewLength = strlen($payload['review_text'] ?? '');
        $estimate += ceil($reviewLength * 0.8); // Increased factor

        return $estimate;
    }

    /**
     * Fix truncated JSON response
     */
    private function fixTruncatedJson(string $content): string
    {
        // Remove trailing incomplete structures
        $content = rtrim($content);

        // Check if JSON ends with incomplete object
        if (substr($content, -1) !== '}') {
            // Find the last complete closing brace
            $lastBracePos = strrpos($content, '}');
            if ($lastBracePos !== false) {
                $content = substr($content, 0, $lastBracePos + 1);
            } else {
                // No closing brace found, try to close it
                $content .= '}';
            }
        }

        // Check for incomplete arrays
        if (substr_count($content, '[') > substr_count($content, ']')) {
            $content .= ']';
        }

        // Check for incomplete strings
        $openQuotes = substr_count($content, '"');
        if ($openQuotes % 2 !== 0) {
            // Odd number of quotes, close the last string
            $content .= '"';
        }

        // Remove trailing commas before closing braces
        $content = preg_replace('/,\s*([\]}])/', '$1', $content);

        return $content;
    }


    /**
     * Fix common JSON issues in OpenAI responses
     */
    private function fixCommonJsonIssues(string $content): string
    {
        // Remove any leading/trailing whitespace
        $content = trim($content);

        // Check if content starts and ends with braces
        if (substr($content, 0, 1) !== '{' || substr($content, -1) !== '}') {
            // Try to find JSON object in the string
            $startPos = strpos($content, '{');
            $endPos = strrpos($content, '}');

            if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
                $content = substr($content, $startPos, $endPos - $startPos + 1);
            }
        }

        // Remove trailing commas before closing braces/brackets
        $content = preg_replace('/,\s*([}\]])/', '$1', $content);

        // Fix unquoted property names
        $content = preg_replace_callback('/([{,]\s*)(\w+)(\s*:\s*)/', function ($matches) {
            return $matches[1] . '"' . $matches[2] . '"' . $matches[3];
        }, $content);

        // Fix unescaped quotes in strings
        $content = preg_replace_callback('/:\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/', function ($matches) {
            $value = $matches[1];
            // Only fix if there are unescaped quotes
            if (preg_match('/(?<!\\\\)"/', $value)) {
                $value = str_replace('"', '\"', $value);
            }
            return ': "' . $value . '"';
        }, $content);

        // Fix truncated boolean values - CORRECTED: preg_replace_callback
        $content = preg_replace_callback('/:\s*(tru|fals|nul)\b/', function ($matches) {
            $value = $matches[1];
            if ($value === 'tru')
                return ': true';
            if ($value === 'fals')
                return ': false';
            if ($value === 'nul')
                return ': null';
            return $matches[0];
        }, $content);

        // Fix truncated strings - CORRECTED: preg_replace_callback
        $content = preg_replace_callback('/:\s*"([^"]*)$/', function ($matches) {
            // If we have an unterminated string, close it
            return ': "' . $matches[1] . '"';
        }, $content);

        // Ensure proper escaping of special characters
        $content = str_replace(
            ["\n", "\r", "\t"],
            ["\\n", "\\r", "\\t"],
            $content
        );

        return $content;
    }

    /**
     * Extract JSON from a string that might have other text
     */
    private function extractJsonFromString(string $content): string
    {
        // Try to find the JSON object
        $startPos = strpos($content, '{');
        $endPos = strrpos($content, '}');

        if ($startPos === false || $endPos === false || $endPos <= $startPos) {
            // Try arrays too
            $startPos = strpos($content, '[');
            $endPos = strrpos($content, ']');
        }

        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $json = substr($content, $startPos, $endPos - $startPos + 1);

            // Validate it looks like JSON
            $firstChar = substr($json, 0, 1);
            $lastChar = substr($json, -1);

            if (
                ($firstChar === '{' && $lastChar === '}') ||
                ($firstChar === '[' && $lastChar === ']')
            ) {
                return $json;
            }
        }

        // If we can't extract, return empty object
        return '{}';
    }

    /**
     * Find approximate line of JSON error
     */
    private function findJsonErrorLine(string $content): string
    {
        $lines = explode("\n", $content);
        $position = 0;

        foreach ($lines as $lineNum => $line) {
            $testJson = implode("\n", array_slice($lines, 0, $lineNum + 1));
            if (json_decode($testJson) === null && json_last_error() !== JSON_ERROR_NONE) {
                // Try to find character position in line
                $testPosition = 0;
                while ($testPosition < strlen($line)) {
                    $testCharJson = substr($line, 0, $testPosition + 1);
                    if (json_decode('{' . $testCharJson) === null) {
                        return 'Line ' . ($lineNum + 1) . ', Char ' . ($testPosition + 1);
                    }
                    $testPosition++;
                }
                return 'Line ' . ($lineNum + 1);
            }
        }

        return 'Unknown position';
    }

    /**
     * Clean JSON content from OpenAI response
     */
    private function cleanJsonContent(string $content): string
    {
        // Remove any leading/trailing whitespace and control characters
        $content = trim($content);

        // Remove any markdown code blocks if present
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = preg_replace('/^```\s*/', '', $content); // Also remove non-json code blocks

        // Replace escaped unicode characters
        $content = str_replace(
            ['\u201c', '\u201d', '\u2018', '\u2019', '\u2026', '\u2014'],
            ['"', '"', "'", "'", '...', '-'],
            $content
        );

        // Remove any BOM (Byte Order Mark)
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        // Remove other control characters except newlines, tabs, and returns
        $content = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Fix common formatting issues
        $content = preg_replace('/\s+/', ' ', $content); // Replace multiple spaces with single space

        return $content;
    }






    /**
     * Get system prompt based on enabled modules
     */
    private function getSystemPrompt(array $enabledModules): string
    {
        $prompt = <<<PROMPT
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
  "rating_comment_alignment": {
    "is_aligned": true|false,
    "mismatch_type": "positive_rating_negative_comment|negative_rating_positive_comment|neutral_mismatch|none",
    "confidence": 0.0 to 1.0,
    "explanation": "Why ratings and comments don't match",
    "key_contradiction": "specific contradiction found"
  },
PROMPT;

        // Add optional modules based on enabledModules array
        if ($enabledModules['category_analysis'] ?? true) {
            $prompt .= <<<PROMPT
  "category_analysis": [
    {
      "main_category": "Staff|Service|Food|Ambiance|Cleanliness|Price|Location|Others",
      "sub_category": "specific aspect",
      "sentiment": "negative|neutral|positive",
      "severity": "low|medium|high",
      "evidence_from_comment": "specific phrase from comment"
    }
  ],
PROMPT;
        } else {
            $prompt .= <<<PROMPT
  "category_analysis": [],
PROMPT;
        }

        if ($enabledModules['staff_intelligence'] ?? true) {
            $prompt .= <<<PROMPT
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
    "risk_level": "low|medium|high",
    "blame_detected": true|false,
    "staff_context": "staff vs process issue"
  },
PROMPT;
        } else {
            $prompt .= <<<PROMPT
  "staff_intelligence": null,
PROMPT;
        }

        if ($enabledModules['service_unit_intelligence'] ?? true) {
            $prompt .= <<<PROMPT
  "service_unit_intelligence": {
    "unit_type": "Room|Table|Equipment|Vehicle|Other",
    "unit_id": "id if available",
    "issues_detected": [],
    "maintenance_required": false,
    "severity": "low|medium|high"
  },
PROMPT;
        } else {
            $prompt .= <<<PROMPT
  "service_unit_intelligence": null,
PROMPT;
        }

        // NEW: Add area-specific insights section
        $prompt .= <<<PROMPT
  "area_insights": [
    {
      "area_id": "area identifier",
      "area_name": "area name",
      "sentiment": "positive|mixed|negative",
      "key_issues": ["list", "of", "issues"],
      "strengths": ["list", "of", "strengths"],
      "supporting_evidence": "text from comment that supports this"
    }
  ],
PROMPT;

        if ($enabledModules['business_recommendations'] ?? true) {
            $prompt .= <<<PROMPT
  "business_insights": {
    "root_cause": "main issue identified",
    "repeat_issue_likelihood": "low|medium|high",
    "impact_level": "low|medium|high",
    "affected_areas": ["list", "of", "areas"]
  },
  "recommendations": {
    "business_actions": ["action items"],
    "staff_actions": ["action items"],
    "immediate_actions": ["urgent actions if needed"],
    "priority": "high|medium|low"
  },
PROMPT;
        } else {
            $prompt .= <<<PROMPT
  "business_insights": {
    "root_cause": "N/A",
    "repeat_issue_likelihood": "N/A",
    "impact_level": "N/A",
    "affected_areas": []
  },
  "recommendations": {
    "business_actions": [],
    "staff_actions": [],
    "immediate_actions": [],
    "priority": "low"
  },
PROMPT;
        }

        if ($enabledModules['alerts'] ?? true) {
            $prompt .= <<<PROMPT
  "alerts": {
    "triggered": true|false,
    "type": "critical|warning|insight|info",
    "priority": "high|medium|low",
    "message": "alert message",
    "reason": "why this alert was triggered",
    "recommended_action": "what should be done"
  },
PROMPT;
        } else {
            $prompt .= <<<PROMPT
  "alerts": {
    "triggered": false,
    "type": "info",
    "priority": "low",
    "message": "Alerts module disabled"
  },
PROMPT;
        }

        // NEW: Add flags section for dashboard insights
        $prompt .= <<<PROMPT
  "flags": [
    {
      "flag_type": "INSIGHT|WARNING|CRITICAL",
      "severity": "low|medium|high",
      "reason": "why this is flagged",
      "recommended_action": "action to take"
    }
  ],
  "staff_impact": {
    "staff_blame_detected": false,
    "note": "clarify if issue is about staff or process"
  },
PROMPT;

        $prompt .= <<<PROMPT
  "explainability": {
    "decision_basis": ["key factors"],
    "confidence_score": 0.0 to 1.0,
    "key_factors": ["important elements"],
    "why_flagged": "explanation for any flags",
    "how_decision_was_made": "decision logic explanation"
  },
  "summary": {
    "one_line": "brief summary",
    "manager_summary": "detailed summary for managers",
    "customer_sentiment_summary": "sentiment summary",
    "overall_assessment": "positive_with_concerns|positive|mixed|negative_with_positives|negative"
  }
}

CRITICAL ANALYSIS GUIDELINES:

1. LANGUAGE: Detect language, translate to English if needed

PROMPT;

        // Add analysis guidelines only for enabled modules
        if ($enabledModules['sentiment_analysis'] ?? true) {
            $prompt .= <<<PROMPT
2. SENTIMENT & RATING-COMMENT ALIGNMENT:
   - Check if numeric ratings match the tone of comments
   - High ratings (4-5) with negative words = "positive_rating_negative_comment"
   - Low ratings (1-2) with positive words = "negative_rating_positive_comment"
   - Examples of mismatch detection:
     * Rating 5, comment "terrible service" = MISMATCH
     * Rating 1, comment "amazing experience" = MISMATCH
     * Rating 4, comment "good but could be better" = ALIGNED (constructive feedback)
   - Provide clear explanation in "rating_comment_alignment.explanation"

PROMPT;
        }

        if ($enabledModules['emotion_detection'] ?? true) {
            $prompt .= "\n3. EMOTION DETECTION:\n   - Angry words = anger\n   - Happy words = joy\n   - Disappointed words = sadness\n   - Fearful words = fear\n   - Frustrated words = anger/disgust";
        }

        if ($enabledModules['abuse_detection'] ?? true) {
            $prompt .= "\n4. MODERATION: Mark as abusive for hate speech, threats, extreme profanity, personal attacks";
        }

        if ($enabledModules['category_analysis'] ?? true) {
            $prompt .= <<<PROMPT
5. CATEGORY ANALYSIS:
   - Staff: Behavior, knowledge, attitude, professionalism
   - Service: Speed, efficiency, wait time, process
   - Food: Quality, taste, presentation, temperature
   - Ambiance: Noise, lighting, music, comfort
   - Cleanliness: Clean, dirty, hygiene, maintenance
   - Price: Value, expensive, affordable, worth it
   - Location: Convenience, parking, accessibility
   - Others: Booking, reservation, website, app

PROMPT;
        }

        if ($enabledModules['staff_intelligence'] ?? true) {
            $prompt .= <<<PROMPT
6. STAFF INTELLIGENCE:
   - Distinguish between staff behavior vs process issues
   - Staff blame: "rude staff" = staff issue
   - Process blame: "slow service" = process issue
   - If staff mentioned negatively but rating is high, note the contradiction
   - Set "staff_blame_detected": true only if criticism is directed at staff personally

PROMPT;
        }

        // NEW: Area insights guidelines
        $prompt .= <<<PROMPT
7. AREA INSIGHTS:
   - Map comments to specific areas mentioned (reception, room, kitchen, etc.)
   - If multiple areas mentioned, analyze each separately
   - Example: "reception was great but room was dirty" → two areas with different sentiments
   - Use "supporting_evidence" to quote specific text from comment
   - Identify which area is most impacted by negative feedback

PROMPT;

        if ($enabledModules['business_recommendations'] ?? true) {
            $prompt .= <<<PROMPT
8. RECOMMENDATIONS:
   - Be specific and actionable
   - Link recommendations to specific areas/issues
   - For rating-comment mismatches: Suggest investigating hidden issues
   - Example: "Review reception staffing during peak hours"

PROMPT;
        }

        if ($enabledModules['alerts'] ?? true) {
            $prompt .= <<<PROMPT
9. ALERTS & FLAGS:
   - "insight" flag for: High ratings with negative comments
   - "warning" flag for: Repeated issues in same area
   - "critical" flag for: Safety issues, abusive content
   - For mismatches: Use "INSIGHT" flag type with "medium" severity
   - Include clear "reason" and "recommended_action"

PROMPT;
        }

        $prompt .= <<<PROMPT

BUSINESS-SPECIFIC ANALYSIS RULES:

1. RATING-COMMENT CONTRADICTIONS:
   - Always check if the comment contradicts the numeric rating
   - A rating of 4/5 with words like "terrible", "awful", "unacceptable" = MISMATCH
   - Explain the contradiction clearly for business owners

2. AREA-SPECIFIC FEEDBACK:
   - Identify which business area is being discussed
   - Separate staff performance from area/process issues
   - Example: "Staff were polite but wait time was long" → Staff: positive, Process: negative

3. FAIR STAFF ASSESSMENT:
   - Don't blame staff for process issues
   - "Long wait time" = process issue, not staff issue
   - "Rude staff" = staff issue
   - Set "staff_blame_detected" accordingly

4. ACTIONABLE INSIGHTS:
   - Provide insights that managers can act on
   - If mismatch found, suggest investigating the specific issue mentioned
   - Example: "Customer rated 5 stars but mentioned long wait time → Investigate reception staffing"

5. DASHBOARD-FRIENDLY OUTPUT:
   - Structure data so it can be displayed in dashboards
   - Use clear categories and labels
   - Include evidence from comments to support decisions

JSON OUTPUT FORMATTING RULES - CRITICAL:
1. Return ONLY valid JSON, no additional text before or after
2. Do NOT wrap JSON in markdown code blocks (no \`\`\`json or \`\`\`)
3. Use ONLY straight double quotes (") for property names and string values
4. Escape ALL double quotes within strings: "staff said \"hello\"" not "staff said "hello""
5. Do NOT add trailing commas: {"a": 1, "b": 2} not {"a": 1, "b": 2,}
6. Ensure ALL strings are properly quoted: "label": "negative" not "label": negative
7. Ensure ALL booleans are lowercase: true/false not True/False
8. Ensure ALL numbers are not quoted: "score": -1.0 not "score": "-1.0"
9. Complete ALL JSON structures - don't truncate arrays or objects
10. If the response is long, ensure max_tokens is sufficient to complete
11. Ensure ALL special characters in strings are properly escaped
12. Make sure to close ALL brackets and braces properly
13. Do not leave any JSON structures incomplete
14. If you reach token limit, prioritize completing the JSON structure over adding more detail

IMPORTANT: If your response gets cut off due to token limits, reduce detail but keep valid JSON structure.
Return COMPLETE JSON even if you need to omit some details. A complete but less detailed JSON is better than truncated JSON.

SPECIAL CHARACTER HANDLING:
- Escape backslashes: "path\\to\\file" becomes "path\\\\to\\\\file"
- Escape double quotes within strings: He said "hello" becomes "He said \\"hello\\""
- Escape newlines: Use \\\\n instead of actual newline in JSON strings
- Escape tabs: Use \\\\t instead of actual tab in JSON strings

TOKEN LIMIT MANAGEMENT:
- If the JSON structure is large, prioritize completing the structure
- You can shorten explanations if needed
- You can reduce the number of items in arrays if needed
- But ALWAYS return valid, complete JSON

TOKEN MANAGEMENT GUIDANCE FOR ENABLED MODULES:

PROMPT;

        // Add token guidance for each enabled module
        if ($enabledModules['category_analysis'] ?? false) {
            $prompt .= <<<PROMPT
- CATEGORY ANALYSIS: Include 2-3 main categories with evidence. Focus on the most significant categories mentioned in the review. For each category, provide specific evidence from the comment.

PROMPT;
        }

        if ($enabledModules['staff_intelligence'] ?? false) {
            $prompt .= <<<PROMPT
- STAFF INTELLIGENCE: Include soft skill scores and specific recommendations if applicable. If staff is mentioned positively, highlight their strengths. If mentioned negatively, provide constructive feedback.

PROMPT;
        }

        if ($enabledModules['business_recommendations'] ?? false) {
            $prompt .= <<<PROMPT
- BUSINESS RECOMMENDATIONS: Provide 2-3 actionable recommendations per area. Focus on the most critical issues first. Make recommendations specific and implementable.

PROMPT;
        }

        if ($enabledModules['alerts'] ?? false) {
            $prompt .= <<<PROMPT
- ALERTS: Trigger alerts only for significant issues. Use appropriate priority levels. Provide clear recommended actions.

PROMPT;
        }

        $prompt .= <<<PROMPT

EFFICIENT TOKEN USAGE STRATEGIES:
1. Keep explanations concise but meaningful
2. Limit arrays to 3-5 items maximum unless more are critical
3. Use brief but descriptive text in evidence fields
4. For long reviews, focus on the most impactful points
5. Balance detail with completeness - it's better to have complete JSON with moderate detail than detailed but truncated JSON

MODULE PRIORITY ORDER (if token constrained):
1. Required modules (language, sentiment, emotion, moderation, rating_comment_alignment)
2. Category analysis
3. Staff intelligence (if staff mentioned)
4. Business recommendations
5. Alerts and flags
6. Area insights
7. Service unit intelligence

You have been allocated sufficient tokens for all enabled modules based on the review length and complexity. Provide complete analysis for all enabled modules while maintaining valid JSON structure.

IMPORTANT REMINDER: The system has calculated appropriate token allocation for all your enabled modules. You do not need to worry about token limits - just provide complete, high-quality analysis for all enabled modules.

Return ONLY the JSON object. Be accurate, fair, and business-focused. For disabled modules, return minimal/empty data as specified.
PROMPT;

        return $prompt;
    }
    /**
     * Create user message for OpenAI
     */

    private function createUserMessage(array $payload, array $enabledModules): string
    {
        $text = $payload['review_text'] ?? '';
        $rating = $payload['rating'] ?? 0;
        $staffInfo = $payload['staff_info'] ?? null;

        $message = "REVIEW TO ANALYZE:\n";
        $message .= "Text: \"{$text}\"\n";
        $message .= "Overall Rating: {$rating}/5\n";

        // Include question ratings if available
        if (!empty($payload['question_ratings'])) {
            $message .= "\nQUESTION RATINGS:\n";
            foreach ($payload['question_ratings'] as $qRating) {
                $message .= "- {$qRating['question_text']}: {$qRating['rating']}/{$qRating['scale']}\n";
            }
        }

        // Only include staff info if staff_intelligence module is enabled
        if (($enabledModules['staff_intelligence'] ?? true) && $staffInfo) {
            $message .= "\nStaff Mentioned: " . ($staffInfo['staff_name'] ?? 'Unknown') . " (ID: " . ($staffInfo['staff_id'] ?? '') . ")\n";
        }

        // Add business services/areas if available
        if (!empty($payload['business_services'])) {
            $message .= "\nBUSINESS AREAS/SERVICES MENTIONED:\n";
            foreach ($payload['business_services'] as $service) {
                $message .= "- {$service['business_service_name']} (Area: {$service['business_area_name']})\n";
            }
        }

        $message .= "\nANALYSIS INSTRUCTIONS:\n";
        $message .= "1. Check if ratings and comments align\n";
        $message .= "2. High ratings (4-5) with negative comments should be flagged as misaligned\n";
        $message .= "3. Provide explanation for any mismatch\n";
        $message .= "4. Identify which specific area/issue is mentioned negatively\n";

        return $message;
    }

    /**
     * Create payload from ReviewNew model
     */

    public function createPayloadFromReview(ReviewNew $review): array
    {
        $text = $review->raw_text ?? $review->comment ?? '';

        // Get question ratings if this is a survey review
        $questionRatings = [];
        if ($review->survey_id && $review->value) {
            foreach ($review->value as $value) {
                if ($value->question_id && $value->rating) {
                    $questionRatings[] = [
                        'question_id' => $value->question_id,
                        'question_text' => $value->question->question_text ?? 'Question',
                        'rating' => $value->rating,
                        'scale' => 5, // Assuming 5-point scale, adjust as needed
                        'category' => $value->question->category ?? 'General'
                    ];
                }
            }
        }

        // Get staff info
        $staffInfo = null;
        if ($review->staff_id) {
            $staff = User::find($review->staff_id);
            if ($staff) {
                $staffInfo = [
                    'staff_id' => $review->staff_id,
                    'staff_name' => trim($staff->first_Name . ' ' . $staff->last_Name),
                    'job_title' => $staff->job_title ?? ''
                ];
            }
        }

        // Get business services with their areas
        $business_services = [];
        foreach ($review->review_business_services as $review_business_service) {
            $business_services[] = [
                'business_service_id' => $review_business_service->business_service_id,
                'business_service_name' => $review_business_service->business_service->name ?? 'Unknown Service',
                'business_area_id' => $review_business_service->business_area_id ?? null,
                'business_area_name' => $review_business_service->business_area->area_name ?? 'Unknown Area',
            ];
        }

        $avgRating = $review->calculated_rating;

        return [
            'review_text' => $text,
            'rating' => $avgRating,
            'question_ratings' => $questionRatings, // Added this
            'staff_info' => $staffInfo,
            'business_services' => $business_services,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'metadata' => [
                'source' => $review->source ?? 'web',
                'language' => $review->language,
                'review_type' => $review->review_type ?? 'text',
                'is_voice' => $review->is_voice_review ?? false,
                'submitted_at' => $review->responded_at ?? \now()->toISOString(),
                'branch_id' => $review->branch_id
            ]
        ];
    }

    /**
     * Extract rating-comment mismatch insights
     */
    public function extractMismatchInsights(array $aiResult, ReviewNew $review): array
    {
        $mismatchData = $aiResult['rating_comment_alignment'] ?? null;

        if (!$mismatchData || $mismatchData['is_aligned']) {
            return [
                'has_mismatch' => false,
                'should_flag' => false
            ];
        }

        // Calculate average rating from question ratings if available
        $avgRating = $review->calculated_rating;



        // Determine flag type based on mismatch
        $shouldFlag = false;
        $flagType = 'none';

        if ($mismatchData['mismatch_type'] === 'positive_rating_negative_comment' && $avgRating >= 3.5) {
            $shouldFlag = true;
            $flagType = 'insight'; // Soft flag for high rating + negative comment
        } elseif ($mismatchData['mismatch_type'] === 'negative_rating_positive_comment' && $avgRating <= 2.5) {
            $shouldFlag = true;
            $flagType = 'warning';
        }

        return [
            'has_mismatch' => true,
            'mismatch_type' => $mismatchData['mismatch_type'],
            'is_aligned' => $mismatchData['is_aligned'],
            'explanation' => $mismatchData['explanation'] ?? '',
            'confidence' => $mismatchData['confidence'] ?? 0.0,
            'should_flag' => $shouldFlag,
            'flag_type' => $flagType,
            'average_rating' => $avgRating
        ];
    }
    /**
     * Analyze a review and save results to database
     */

    // In analyzeReview method, add debugging:

    public function analyzeReview(ReviewNew $review, bool $forceReprocess = false): array
    {
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
            $payload = $this->createPayloadFromReview($review);
            // Log the payload for debugging
            Log::debug('Review payload for OpenAI', [
                'review_id' => $review->id,
                'text_preview' => substr($payload['review_text'] ?? '', 0, 100),
                'text_length' => strlen($payload['review_text'] ?? ''),
                'has_special_chars' => preg_match('/[^\x20-\x7E]/', $payload['review_text'] ?? '') ? 'yes' : 'no'
            ]);
            $businessId = $review->business_id;

            // Get enabled modules for this business
            $enabledModules = $this->getBusinessAiModules($businessId);

            Log::debug('Analyzing review with modules', [
                'review_id' => $review->id,
                'business_id' => $businessId,
                'enabled_modules' => $enabledModules
            ]);

            $openAIResult = $this->processReviewWithOpenAI($payload, $enabledModules);

            // Convert to database format
            $dbData = $this->convertForDatabase($openAIResult, $review, $enabledModules);

            // Update the review model BEFORE rule evaluation so rules can access the data
            $review->fill($dbData);



            // Save the updated review
            $review->save();

            Log::info('Review analysis completed', [
                'review_id' => $review->id,
                'sentiment' => $dbData['sentiment_label'] ?? 'unknown',
                'modules_used' => $enabledModules
            ]);

            return array_merge($dbData, [
                'status' => 'success',
                'message' => 'Analysis completed successfully',
                'enabled_modules' => $enabledModules
            ]);
        } catch (\Exception $e) {
            Log::error('Review analysis failed', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw exception to allow caller to handle failure
            throw $e;
        }
    }

    private function extractIssuesFromExplanation(string $explanation): array
    {
        $issues = [];

        // Common patterns in explanations
        $patterns = [
            '/wait.*time/i' => 'Wait Time',
            '/slow.*service/i' => 'Slow Service',
            '/rude|impolite|unprofessional/i' => 'Staff Behavior',
            '/dirty|clean|hygiene/i' => 'Cleanliness',
            '/expensive|price|cost/i' => 'Pricing',
            '/noisy|loud|quiet/i' => 'Noise Level',
            '/broken|damaged|not working/i' => 'Maintenance',
            '/disorganised|chaotic|messy/i' => 'Organization',
            '/cold|hot|temperature/i' => 'Temperature',
            '/small|cramped|space/i' => 'Space/Size',
            '/crowded|busy|packed/i' => 'Crowding',
            '/late|delay|on time/i' => 'Timeliness',
            '/mistake|error|wrong/i' => 'Accuracy',
            '/quality|standard/i' => 'Quality',
            '/staff.*attitude/i' => 'Staff Attitude',
            '/food.*quality/i' => 'Food Quality',
            '/service.*speed/i' => 'Service Speed'
        ];

        foreach ($patterns as $pattern => $issue) {
            if (preg_match($pattern, $explanation)) {
                $issues[] = $issue;
            }
        }

        return array_unique($issues);
    }

    private function generateMismatchRecommendations(
        int $mismatchCount,
        int $totalReviews,
        array $commonIssues,
        array $affectedAreas,
        string $mostCommonType
    ): array {
        $recommendations = [];
        $mismatchPercentage = $totalReviews > 0 ? ($mismatchCount / $totalReviews) * 100 : 0;

        if ($mismatchPercentage > 15) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'High Rate of Hidden Issues',
                'description' => "{$mismatchPercentage}% of reviews show rating-comment mismatch. Customers may be hesitant to give low ratings.",
                'action' => 'Review survey design and encourage honest feedback'
            ];
        }

        if (!empty($commonIssues)) {
            $topIssue = array_key_first($commonIssues);
            $issueCount = $commonIssues[$topIssue];

            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Common Hidden Issue: ' . $topIssue,
                'description' => "Mentioned {$issueCount} times in mismatched reviews",
                'action' => 'Investigate and address ' . strtolower($topIssue)
            ];
        }

        if (!empty($affectedAreas)) {
            $topArea = array_key_first($affectedAreas);

            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Area Needing Attention: ' . $topArea,
                'description' => 'Most frequently mentioned in mismatched feedback',
                'action' => 'Conduct focused review of ' . $topArea . ' operations'
            ];
        }

        if ($mostCommonType === 'positive_rating_negative_comment') {
            $recommendations[] = [
                'priority' => 'low',
                'title' => 'Customer Rating Behavior',
                'description' => 'Customers giving high ratings but mentioning issues',
                'action' => 'Consider adding "What could we improve?" as a follow-up question'
            ];
        }

        if (count($recommendations) === 0) {
            $recommendations[] = [
                'priority' => 'low',
                'title' => 'Monitor Mismatch Patterns',
                'description' => 'Current mismatch levels are within acceptable range',
                'action' => 'Continue monitoring for patterns'
            ];
        }

        return $recommendations;
    }

    public function getMismatchInsightsForDashboard(int $businessId, $startDate, $endDate): array
    {
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('rating_comment_mismatch', true)
            ->where('is_ai_processed', true)
            ->with(['value.question', 'staff', 'review_business_services.business_service', 'review_business_services.business_area'])
            ->globalReviewFilters(0)
            ->get();

        if ($reviews->isEmpty()) {
            return [
                'has_data' => false,
                'message' => 'No rating-comment mismatches detected in this period'
            ];
        }

        $totalReviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->globalReviewFilters(0)
            ->count();

        $mismatchByType = [
            'positive_rating_negative_comment' => 0,
            'negative_rating_positive_comment' => 0,
            'neutral_mismatch' => 0
        ];

        $commonIssues = [];
        $affectedAreas = [];
        $staffInvolved = [];
        $sampleReviews = [];
        $ratingPatterns = [];

        foreach ($reviews as $review) {
            $insights = is_array($review->mismatch_insights) ? $review->mismatch_insights : json_decode($review->mismatch_insights ?? '{}', true);
            $mismatchType = $insights['mismatch_type'] ?? 'unknown';

            if (isset($mismatchByType[$mismatchType])) {
                $mismatchByType[$mismatchType]++;
            }

            // Track rating pattern
            $avgRating = $review->calculated_rating;


            if ($avgRating > 0) {
                $ratingKey = floor($avgRating) . '_stars';
                $ratingPatterns[$ratingKey] = ($ratingPatterns[$ratingKey] ?? 0) + 1;
            }

            // Extract common issues
            $explanation = $insights['explanation'] ?? '';
            if ($explanation) {
                $issues = $this->extractIssuesFromExplanation($explanation);
                foreach ($issues as $issue) {
                    $commonIssues[$issue] = ($commonIssues[$issue] ?? 0) + 1;
                }
            }

            // Track affected areas
            foreach ($review->review_business_services as $service) {
                $areaName = $service->business_area_name ?? 'Unknown';
                $affectedAreas[$areaName] = ($affectedAreas[$areaName] ?? 0) + 1;
            }

            // Track staff involvement
            if ($review->staff_id) {
                $staffName = $review->staff->name ?? 'Unknown Staff';
                $staffInvolved[$staffName] = ($staffInvolved[$staffName] ?? 0) + 1;
            }

            $avgRating = $review->calculated_rating;

            // Collect sample reviews for display
            if (count($sampleReviews) < 5) {
                $sampleReviews[] = [
                    'id' => $review->id,
                    'rating' => $avgRating,
                    'comment' => substr($review->comment ?? '', 0, 150) . '...',
                    'mismatch_type' => $mismatchType,
                    'explanation' => substr($explanation, 0, 100) . '...',
                    'date' => $review->created_at->format('M d, Y')
                ];
            }
        }

        arsort($commonIssues);
        arsort($affectedAreas);
        arsort($staffInvolved);

        $totalMismatches = array_sum($mismatchByType);
        $mostCommonType = array_keys($mismatchByType, max($mismatchByType))[0] ?? 'none';

        // Generate recommendations
        $recommendations = $this->generateMismatchRecommendations(
            $totalMismatches,
            $totalReviews,
            $commonIssues,
            $affectedAreas,
            $mostCommonType
        );

        return [
            'has_data' => true,
            'summary' => [
                'total_mismatches' => $totalMismatches,
                'total_reviews' => $totalReviews,
                'mismatch_percentage' => $totalReviews > 0 ? round(($totalMismatches / $totalReviews) * 100, 1) : 0,
                'most_common_type' => $mostCommonType,
                'description' => $mostCommonType === 'positive_rating_negative_comment'
                    ? "Customers are giving high ratings but mentioning issues in comments"
                    : "Rating patterns suggest potential rating inflation or other issues"
            ],
            'breakdown' => [
                'by_type' => $mismatchByType,
                'by_rating' => $ratingPatterns,
                'common_issues' => array_slice($commonIssues, 0, 10, true),
                'affected_areas' => array_slice($affectedAreas, 0, 5, true),
                'staff_involved' => array_slice($staffInvolved, 0, 5, true)
            ],
            'trend_analysis' => [
                'primary_issue' => !empty($commonIssues) ? array_key_first($commonIssues) : 'None detected',
                'most_affected_area' => !empty($affectedAreas) ? array_key_first($affectedAreas) : 'General',
                'risk_level' => ($totalMismatches / max(1, $totalReviews)) > 0.2 ? 'High' : 'Medium',
                'customer_trend' => $mostCommonType === 'positive_rating_negative_comment'
                    ? 'Customers hesitant to give low ratings'
                    : 'Inconsistent feedback patterns'
            ],
            'recommendations' => $recommendations,
            'sample_reviews' => $sampleReviews,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];
    }

    public function extractCommonMismatchIssues($mismatchReviews): array
    {
        $issues = [];

        foreach ($mismatchReviews as $review) {
            $insights = json_decode($review->mismatch_insights ?? '{}', true);
            $explanation = $insights['explanation'] ?? '';

            if (empty($explanation)) {
                continue;
            }

            // Extract common keywords from explanation
            $keywords = [
                'wait' => 'Wait Time',
                'slow' => 'Slow Service',
                'rude' => 'Rudeness',
                'dirty' => 'Cleanliness',
                'expensive' => 'Pricing',
                'noisy' => 'Noise',
                'broken' => 'Maintenance',
                'disorganised' => 'Organization',
                'unprofessional' => 'Professionalism',
                'cold' => 'Temperature/Food',
                'hot' => 'Temperature',
                'small' => 'Size/Space',
                'crowded' => 'Crowding',
                'late' => 'Timeliness',
                'mistake' => 'Accuracy'
            ];

            foreach ($keywords as $keyword => $label) {
                if (stripos($explanation, $keyword) !== false) {
                    $issues[$label] = ($issues[$label] ?? 0) + 1;
                }
            }
        }

        arsort($issues);
        return $issues;
    }



    public function generateRecommendations(array $aiResult, array $enabledModules): array
    {
        if (!in_array('recommendations', $enabledModules) && !in_array('business_recommendations', $enabledModules)) {
            return [];
        }

        return $aiResult['recommendations'] ?? [];
    }

    /**
     * Extract detailed insights from OpenAI result
     */
    private function extractInsights(array $result, array $enabledModules): array
    {
        $insights = [];

        if (isset($result['category_analysis'])) {
            $insights['category_analysis'] = $result['category_analysis'];
        }

        if (isset($result['staff_intelligence'])) {
            $insights['staff_intelligence'] = $result['staff_intelligence'];
        }

        if (isset($result['area_insights'])) {
            $insights['area_insights'] = $result['area_insights'];
        }

        if (isset($result['business_insights'])) {
            $insights['business_insights'] = $result['business_insights'];
        }

        if (isset($result['alerts'])) {
            $insights['alerts'] = $result['alerts'];
        }

        if (isset($result['flags'])) {
            $insights['flags'] = $result['flags'];
        }

        if (isset($result['explainability'])) {
            $insights['explainability'] = $result['explainability'];
        }

        if (isset($result['summary'])) {
            $insights['summary'] = $result['summary'];
        }

        return $insights;
    }


    /**
     * Convert OpenAI result to database format
     */
    public function convertForDatabase(array $result, ReviewNew $review, array $enabledModules = []): array
    {
        $mismatchInsights = $this->extractMismatchInsights($result, $review);

        $dbData = [
            'sentiment_score' => $result['sentiment']['score'] ?? 0,
            'sentiment_label' => strtolower($result['sentiment']['label'] ?? 'neutral'),
            'emotion' => $result['emotion'] ?? ["primary" => "neutral", "intensity" => "low"],
            'is_abusive' => $result['flagging']['is_abusive'] ?? false,
            'is_ai_processed' => true,
            'ai_confidence' => $result['confidence_score'] ?? 0.8,
            'ai_processed_at' => \now(),
            'ai_model' => Config::get('services.openai.model', 'gpt-4o-mini'),

            // Mismatch data
            'rating_comment_mismatch' => $mismatchInsights['has_mismatch'],
            'mismatch_insights' => json_encode($mismatchInsights),

            // Store detailed insights and tags
            'ai_insights' => $this->extractInsights($result, $enabledModules),
            'ai_recommendations' => $this->generateRecommendations($result, $enabledModules),

            // Map additional fields for dashboard and reporting
            'language' => $result['language']['detected'] ?? 'en',
            'summary' => $result['summary']['one_line'] ?? ($result['summary']['manager_summary'] ?? ''),
            'openai_raw_response' => $result,
            'key_phrases' => $result['tags'] ?? [],
            'topics' => $result['category_analysis'] ?? [],
            'service_analysis' => $result['category_analysis'] ?? [],
            'moderation_results' => $result['flagging'] ?? [],
            'ai_suggestions' => $result['recommendations']['business_actions'] ?? [],
            'staff_suggestions' => $result['recommendations']['staff_actions'] ?? [],
            'review_type' => $review->review_type ?? ($review->is_voice_review ? 'voice' : 'text'),
        ];


        return $dbData;
    }




    public function trackTokenUsage(
        ?int $businessId,
        ?int $reviewId,
        ?int $branchId,
        string $model,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        array $metadata = []
    ): void {
        try {
            $cost = $this->calculateEstimatedCost($model, $promptTokens, $completionTokens);

            \App\Models\OpenAITokenUsage::create([
                'business_id' => $businessId,
                'review_id' => $reviewId,
                'branch_id' => $branchId,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost' => $cost,
                'metadata' => $metadata,
                'created_at' => \now(),
            ]);

            // Note: Tokens are now checked against the limit in Businesses table before processing.
        } catch (\Exception $e) {
            Log::error('Failed to track OpenAI token usage', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update business AI module stats
     */
    public function updateBusinessAiModule(int $businessId, int $tokens, float $cost): void
    {
        try {
            $module = BusinessAiModule::where('business_id', $businessId)->first();
            if ($module) {
                $module->increment('total_tokens_used', $tokens);
                $module->increment('total_cost_usd', $cost);
                $module->last_used_at = \now();
                $module->save();
            }
        } catch (\Exception $e) {
            Log::error('Failed to update business AI module stats', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get enabled modules for a business
     */
    public function getEnabledModules(int $businessId): array
    {
        $modules = BusinessAiModule::where('business_id', $businessId)->first();

        if ($modules) {
            return $modules->getEnabledModules();
        }

        return $this->getEnabledModulesFromConfig();
    }

    /**
     * Get default enabled modules from config
     */
    private function getEnabledModulesFromConfig(): array
    {
        return Config::get('services.openai.default_modules', [
            'sentiment_analysis',
            'emotion_detection',
            'tagging',
            'flagging'
        ]);
    }

    /**
     * Calculate estimated cost based on model and token usage.
     * This is a placeholder and should be updated with actual pricing.
     */
    private function calculateEstimatedCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Example pricing (replace with actual OpenAI pricing)
        $pricing = [
            'gpt-4o-mini' => ['input_per_million' => 0.15, 'output_per_million' => 0.60],
            'gpt-4o' => ['input_per_million' => 5.00, 'output_per_million' => 15.00],
            // Add other models as needed
        ];

        $modelPricing = $pricing[$model] ?? $pricing['gpt-4o-mini']; // Default to gpt-4o-mini

        $inputCost = ($promptTokens / 1_000_000) * $modelPricing['input_per_million'];
        $outputCost = ($completionTokens / 1_000_000) * $modelPricing['output_per_million'];

        return round($inputCost + $outputCost, 6); // Round to 6 decimal places for cents
    }


    /**
     * Get token usage statistics for a business
     */
    public function getTokenUsageStatistics(int $businessId, string $period = 'month'): array
    {
        $query = OpenAITokenUsage::where('business_id', $businessId);

        $dateField = match ($period) {
            'day' => \now()->subDay(),
            'week' => \now()->subWeek(),
            'month' => \now()->subMonth(),
            'quarter' => \now()->subQuarter(),
            'year' => \now()->subYear(),
            default => \now()->subMonth()
        };

        $query->where('created_at', '>=', $dateField);

        $stats = $query->selectRaw('
            SUM(prompt_tokens) as total_prompt_tokens,
            SUM(completion_tokens) as total_completion_tokens,
            SUM(total_tokens) as total_tokens,
            SUM(estimated_cost) as total_cost,
            COUNT(*) as total_requests,
            AVG(total_tokens) as avg_tokens_per_request
        ')->first();

        return [
            'period' => $period,
            'total_prompt_tokens' => $stats->total_prompt_tokens ?? 0,
            'total_completion_tokens' => $stats->total_completion_tokens ?? 0,
            'total_tokens' => $stats->total_tokens ?? 0,
            'total_cost' => $stats->total_cost ?? 0,
            'total_requests' => $stats->total_requests ?? 0,
            'avg_tokens_per_request' => $stats->avg_tokens_per_request ?? 0,
        ];
    }

    /**
     * Update business AI modules
     */
    public function updateBusinessAiModules(int $businessId, array $modules): bool
    {
        try {
            foreach ($modules as $moduleName => $isEnabled) {
                $module = \App\Models\Module::where('name', $moduleName)->first();
                if ($module) {
                    \App\Models\BusinessModule::updateOrCreate(
                        ['business_id' => $businessId, 'module_id' => $module->id],
                        ['is_enabled' => (bool)$isEnabled]
                    );
                }
            }

            Log::info('Business modules updated', [
                'business_id' => $businessId,
                'modules' => $modules
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update business modules', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Legacy compatibility method
     */
    public function processReview(string $text, $staffId = null, $businessId = null): array
    {
        $review = new ReviewNew([
            'raw_text' => $text,
            'staff_id' => $staffId,
            'business_id' => $businessId,
            'comment' => $text
        ]);

        return $this->analyzeReview($review);
    }

    /**
     * Batch process reviews
     */
    public function batchProcessReviews(array $reviewIds): array
    {
        $results = [];
        $reviews = ReviewNew::whereIn('id', $reviewIds)
            ->where('is_ai_processed', 0)
            ->get();

        foreach ($reviews as $review) {
            try {
                $result = $this->analyzeReview($review);
                $results[$review->id] = [
                    'success' => true,
                    'sentiment' => $result['sentiment_label'] ?? 'unknown',
                    'emotion' => $result['emotion'] ?? ["primary" => "neutral", "intensity" => "low"]
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
