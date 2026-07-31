<?php

namespace App\Services\AIProcessor;

use App\Models\BusinessAiModule;
use App\Models\ReviewNew;
use App\Models\User;
use App\Models\OpenAITokenUsage;
use App\Models\Tag;
use App\Services\Rule\RuleEngineService;
use App\Services\Rule\RuleExecutionService;
use App\Models\AiRule;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class OpenAIProcessorService
{
    private RuleEngineService $ruleEngineService;
    private RuleExecutionService $ruleExecutionService;

    public function __construct(
        RuleEngineService $ruleEngineService,
        RuleExecutionService $ruleExecutionService
    ) {
        $this->ruleEngineService = $ruleEngineService;
        $this->ruleExecutionService = $ruleExecutionService;
    }

    private function moduleEnabled(array $enabledModules, string $moduleName, bool $default = false): bool
    {
        return array_key_exists($moduleName, $enabledModules)
            ? (bool) $enabledModules[$moduleName]
            : $default;
    }

    private function normalizeSentimentScore($score): float
    {
        $score = is_numeric($score) ? (float) $score : (float) config('ai.topics.intensity_mapping.default', 0.5);

        // Backward safety: old prompt allowed -1.0 to 1.0.
        if ($score < 0) {
            $score = ($score + 1) / 2;
        }

        // Backward safety if a future response accidentally returns 0-100.
        if ($score > 1) {
            $score = $score / 100;
        }

        return round(max(0, min(1, $score)), 4);
    }

    private function normalizeSentimentLabel(?string $label, float $score): string
    {
        $label = strtolower((string) ($label ?? ''));

        return match ($label) {
            'very_positive', 'positive' => 'positive',
            'very_negative', 'negative' => 'negative',
            'neutral' => 'neutral',
            default => RuleEngineService::getSentimentLabelFromScore($score),
        };
    }

    private function buildKeyPhrases(array $result): array
    {
        $staffMentions = [];
        $staff = $result['staff_intelligence'] ?? null;

        if (is_array($staff) && ($staff['mentioned_explicitly'] ?? false)) {
            $staffMentions[] = [
                'id' => $staff['staff_id'] ?? null,
                'name' => $staff['staff_name'] ?? null,
                'sentiment' => $staff['sentiment_towards_staff'] ?? null,
                'risk_level' => $staff['risk_level'] ?? null,
                'blame_detected' => $staff['blame_detected'] ?? false,
            ];
        }

        $areasMentioned = [];
        foreach (($result['area_insights'] ?? []) as $area) {
            if (!is_array($area)) {
                continue;
            }

            $areasMentioned[] = [
                'id' => $area['area_id'] ?? null,
                'name' => $area['area_name'] ?? null,
                'sentiment' => $area['sentiment'] ?? null,
                'issues' => $area['key_issues'] ?? [],
                'strengths' => $area['strengths'] ?? [],
            ];
        }

        return [
            'tags' => $result['tags'] ?? [],
            'staff_mentions' => $staffMentions,
            'areas_mentioned' => $areasMentioned,
        ];
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
                    Log::info('OpenAI Cache Hit', [
                        'review_id' => $payload['review_id'] ?? 'unknown',
                        'cache_key' => $cacheKey
                    ]);
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

            $requestPayload = [
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
            ];

            log_message([
                'type' => 'REQUEST',
                'action' => 'analyzeReview',
                'payload' => $requestPayload
            ], 'openai_calls.log');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(config('ai.openai.request.process_timeout') ?? 60)
                ->retry(config('ai.openai.request.retry_times') ?? 3, config('ai.openai.request.retry_sleep') ?? 1000)
                ->post('https://api.openai.com/v1/chat/completions', $requestPayload);

            if ($response->failed()) {
                log_message([
                    'type' => 'RESPONSE_ERROR',
                    'action' => 'analyzeReview',
                    'status' => $response->status(),
                    'error' => $response->body()
                ], 'openai_calls.log');

                Log::error('OpenAI API failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'headers' => $response->headers()
                ]);
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $data = $response->json();

            log_message([
                'type' => 'RESPONSE_SUCCESS',
                'action' => 'analyzeReview',
                'status' => $response->status(),
                'response' => $data
            ], 'openai_calls.log');


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

            // Save token usage to DB against this business
            if (!empty($data['usage'])) {
                try {
                    $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
                    $completionTokens = $data['usage']['completion_tokens'] ?? 0;
                    $totalTokens = $data['usage']['total_tokens'] ?? 0;
                    \App\Models\OpenAITokenUsage::create([
                        'business_id' => $payload['business_id'] ?? null,
                        'review_id' => $payload['review_id'] ?? null,
                        'branch_id' => $payload['branch_id'] ?? null,
                        'model' => $data['model'] ?? $requestPayload['model'],
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_tokens' => $totalTokens,
                        'estimated_cost' => \App\Models\OpenAITokenUsage::calculateCost(
                            $data['model'] ?? $requestPayload['model'],
                            $promptTokens,
                            $completionTokens
                        ),
                        'metadata' => ['action' => 'analyzeReview'],
                        'created_at' => now(),
                    ]);
                } catch (\Exception $tokenEx) {
                    Log::warning('Failed to save OpenAI token usage', ['error' => $tokenEx->getMessage()]);
                }
            }

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

            // Reset rules before re-evaluating
            $result['rule_outcomes'] = [];

            // Execute rules
            $businessId = $payload['business_id'] ?? null;
            if ($businessId) {
                $activeRules = AiRule::where('business_id', $businessId)
                    ->where('enabled', true)
                    ->get();

                foreach ($activeRules as $rule) {
                    $outcome = $this->ruleExecutionService->evaluate($rule, $result, $payload);
                    if ($outcome) {
                        $result['rule_outcomes'][] = $outcome;
                    }
                }
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

        // Remove markdown code block markers if present
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
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
You are FeedGenius AI, an AI-powered Customer Experience Intelligence Engine.
Your job is to analyse ONE customer review and return ONLY valid JSON in this exact structure.
Do NOT return markdown.
Do NOT explain your reasoning.
Do NOT return additional text.
Your responsibilities are:
1. Detect language.
2. Translate to English if required.
3. Analyse customer sentiment.
4. Detect customer emotion.
5. Extract topics mentioned.
6. Extract positive aspects.
7. Extract negative aspects.
8. Identify business issues.
9. Estimate issue severity.
10. Detect abusive language.
11. Detect sarcasm where reasonably confident.
12. Estimate spam/fake probability.
13. Generate a concise review summary.
14. Suggest practical improvements.
15. Return confidence scores.

Use ONLY the review provided.
Never invent information.
If something is not mentioned, return an empty array or null.
Return JSON ONLY.

Expected JSON Structure:
{
  "language": {
    "detected": "en",
    "translated_text": null
  },
  "sentiment": {
    "label": "negative|neutral|positive",
    "confidence": 0.0 to 1.0
  },
  "emotion": {
    "primary": "joy|sadness|anger|fear|surprise|disgust|frustration|satisfaction|neutral",
    "intensity": "low|medium|high",
    "confidence": 0.0 to 1.0
  },
  "topics": ["list", "of", "topics"],
  "positive_aspects": ["list", "of", "positive", "aspects"],
  "negative_aspects": ["list", "of", "negative", "aspects"],
  "issues": [
    {
      "category": "category name",
      "severity": "low|medium|high"
    }
  ],
  "abusive_language": {
    "detected": true|false
  },
  "sarcasm": {
    "detected": true|false
  },
  "spam_probability": 0.0 to 1.0,
  "summary": "concise review summary",
  "recommendations": ["list", "of", "practical", "improvements"]
}

BUSINESS CONFIGURATION ENFORCEMENT RULES:
1. Only classify issue categories under the "issues" array into the configured business areas and services passed in the user prompt.
2. If none of the configured business areas or services match the issue, default the category to "Others".
3. If multiple configured areas or services are mentioned in the same review, return ALL matching categories in the issues array. Do not force a single category when multiple valid matches exist.
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
        $analysisType = trim($text) === '' ? 'questionnaire_only' : 'comment';

        $message = "Analysis Type:\n{$analysisType}\n\n";
        $message .= "Business Type:\n" . ($payload['business_type'] ?? 'Restaurant') . "\n\n";
        
        $message .= "Business Configuration\n";
        if (!empty($payload['all_areas'])) {
            $message .= "Areas:\n";
            foreach ($payload['all_areas'] as $area) {
                $message .= "- {$area}\n";
            }
        }
        if (!empty($payload['all_services'])) {
            $message .= "Services:\n";
            foreach ($payload['all_services'] as $srv) {
                $message .= "- {$srv}\n";
            }
        }
        $message .= "\n";
        
        if ($analysisType === 'comment') {
            $message .= "Review Comment:\n\"{$text}\"\n\n";
        }
        $message .= "Overall Rating:\n{$rating}\n\n";
        
        if (!empty($payload['question_ratings'])) {
            $message .= "Survey Answers:\n";
            foreach ($payload['question_ratings'] as $qRating) {
                $message .= "- {$qRating['question_text']} = {$qRating['rating']}/{$qRating['scale']}\n";
            }
            $message .= "\n";
        }
        
        if (!empty($payload['selected_labels'])) {
            $message .= "Selected Labels:\n";
            foreach ($payload['selected_labels'] as $label) {
                $message .= "- {$label}\n";
            }
            $message .= "\n";
        }

        if ($this->moduleEnabled($enabledModules, 'staff_intelligence') && $staffInfo) {
            $message .= "Staff Mentioned:\n- Name: " . ($staffInfo['staff_name'] ?? 'Unknown') . " (ID: " . ($staffInfo['staff_id'] ?? '') . ")\n\n";
        }
        
        $message .= "Return JSON only.";

        return $message;
    }

    /**
     * Create payload from ReviewNew model
     */

    public function createPayloadFromReview(ReviewNew $review): array
    {
        $text = $review->raw_text ?? $review->comment ?? '';

        // Get question ratings and selected labels if this is a survey review
        $questionRatings = [];
        $selectedLabels = [];

        if ($review->survey_id) {
            $values = $review->relationLoaded('value')
                ? $review->value
                : $review->value()->with(['question', 'tags'])->get();

            $starIds = $values
                ->pluck('star_id')
                ->filter()
                ->unique()
                ->values();

            $starValuesById = $starIds->isEmpty()
                ? collect()
                : \App\Models\Star::whereIn('id', $starIds)->pluck('value', 'id');

            foreach ($values as $value) {
                $rating = $value->star_id
                    ? ($starValuesById[$value->star_id] ?? null)
                    : null;

                if ($value->question_id && $rating !== null) {
                    $questionRatings[] = [
                        'question_id' => $value->question_id,
                        'question_text' => $value->question->question_text ?? 'Question',
                        'rating' => (float) $rating,
                        'scale' => 5,
                        'category' => $value->question->category ?? 'General',
                    ];
                }

                if ($value->relationLoaded('tags') || $value->tags()->exists()) {
                    foreach ($value->tags as $tag) {
                        $selectedLabels[] = $tag->name;
                    }
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
        foreach ($review->business_services as $review_business_service) {
            $business_services[] = [
                'business_service_id' => $review_business_service->business_service_id,
                'business_service_name' => $review_business_service->business_service->name ?? 'Unknown Service',
                'business_area_id' => $review_business_service->business_area_id ?? null,
                'business_area_name' => $review_business_service->business_area->area_name ?? 'Unknown Area',
            ];
        }

        $business = \App\Models\Business::find($review->business_id);
        $businessType = $business ? ($business->business_type ?? 'Restaurant') : 'Restaurant';
        
        $allAreas = [];
        $allServices = [];
        if ($business) {
            $allAreas = \App\Models\BusinessArea::where('business_id', $business->id)->active()->pluck('area_name')->toArray();
            $allServices = \App\Models\BusinessService::where('business_id', $business->id)->active()->pluck('name')->toArray();
        }

        $avgRating = $review->calculated_rating;

        return [
            'business_type' => $businessType,
            'all_areas' => $allAreas,
            'all_services' => $allServices,
            'review_text' => $text,
            'rating' => $avgRating,
            'question_ratings' => $questionRatings, // Added this
            'selected_labels' => array_unique($selectedLabels),
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

        $highThreshold = config('ai.openai.anomalies.mismatch_high_rating', 4.0);
        $lowThreshold = config('ai.openai.anomalies.mismatch_low_rating', 2.0);

        if ($mismatchData['mismatch_type'] === 'positive_rating_negative_comment' && $avgRating >= $highThreshold) {
            $shouldFlag = true;
            $flagType = 'insight'; // Soft flag for high rating + negative comment
        } elseif ($mismatchData['mismatch_type'] === 'negative_rating_positive_comment' && $avgRating <= $lowThreshold) {
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
    private function normalizeOpenAIResult(array $result, ReviewNew $review): array
    {
        $confidence = $result['sentiment']['confidence'] ?? 0.85;
        $label = strtolower($result['sentiment']['label'] ?? 'neutral');
        
        // Calculate sentiment score
        $sentimentScore = 0.5;
        if ($label === 'positive') {
            $sentimentScore = 0.5 + ($confidence / 2);
        } elseif ($label === 'negative') {
            $sentimentScore = 0.5 - ($confidence / 2);
        }

        // Build category_analysis from issues and positive/negative aspects
        $categoryAnalysis = [];
        $issues = $result['issues'] ?? [];
        foreach ($issues as $issue) {
            $categoryAnalysis[] = [
                'main_category' => $issue['category'] ?? 'Others',
                'sub_category' => '',
                'sentiment' => 'negative',
                'severity' => $issue['severity'] ?? 'medium',
                'evidence_from_comment' => ''
            ];
        }

        $positives = $result['positive_aspects'] ?? [];
        foreach ($positives as $pos) {
            $categoryAnalysis[] = [
                'main_category' => $pos,
                'sub_category' => '',
                'sentiment' => 'positive',
                'severity' => 'low',
                'evidence_from_comment' => ''
            ];
        }

        // Build moderation
        $abusive = $result['abusive_language']['detected'] ?? false;
        $moderation = [
            'is_abusive' => $abusive,
            'safe_for_public_display' => !$abusive,
            'issues_found' => $abusive ? ['abusive language'] : [],
            'severity' => $abusive ? 'high' : 'low'
        ];

        // Build explainability
        $explainability = [
            'decision_basis' => $result['topics'] ?? [],
            'confidence_score' => $confidence,
            'key_factors' => $result['topics'] ?? [],
            'why_flagged' => '',
            'how_decision_was_made' => 'AI classification'
        ];

        // Build summary
        $summary = [
            'one_line' => $result['summary'] ?? '',
            'manager_summary' => $result['summary'] ?? '',
            'customer_sentiment_summary' => $result['summary'] ?? '',
            'overall_assessment' => $label
        ];

        // Build recommendations
        $recommendations = [
            'business_actions' => $result['recommendations'] ?? [],
            'staff_actions' => [],
            'immediate_actions' => [],
            'priority' => count($issues) > 0 ? 'medium' : 'low'
        ];

        // Calculate rating comment mismatch deterministically
        $rating = $review->calculated_rating;
        $isAligned = true;
        $mismatchType = 'none';
        $explanation = 'Rating and sentiment are aligned.';
        $keyContradiction = 'none';

        if ($rating >= 4.0 && $label === 'negative') {
            $isAligned = false;
            $mismatchType = 'positive_rating_negative_comment';
            $explanation = "Customer rated the review high ({$rating}) but the review text was analyzed as negative.";
            $keyContradiction = "High rating vs Negative comment sentiment.";
        } elseif ($rating <= 2.0 && $label === 'positive') {
            $isAligned = false;
            $mismatchType = 'negative_rating_positive_comment';
            $explanation = "Customer rated the review low ({$rating}) but the review text was analyzed as positive.";
            $keyContradiction = "Low rating vs Positive comment sentiment.";
        }

        $ratingCommentAlignment = [
            'is_aligned' => $isAligned,
            'mismatch_type' => $mismatchType,
            'confidence' => $confidence,
            'explanation' => $explanation,
            'key_contradiction' => $keyContradiction
        ];

        return [
            'language' => $result['language'] ?? ['detected' => 'en', 'translated_text' => null],
            'sentiment' => [
                'label' => $label,
                'score' => $sentimentScore
            ],
            'emotion' => [
                'primary' => $result['emotion']['primary'] ?? 'neutral',
                'intensity' => $result['emotion']['intensity'] ?? 'medium'
            ],
            'moderation' => $moderation,
            'rating_comment_alignment' => $ratingCommentAlignment,
            'category_analysis' => $categoryAnalysis,
            'staff_intelligence' => null,
            'service_unit_intelligence' => null,
            'area_insights' => [],
            'business_insights' => [
                'root_cause' => $result['summary'] ?? '',
                'repeat_issue_likelihood' => 'medium',
                'impact_level' => 'medium',
                'affected_areas' => $result['topics'] ?? []
            ],
            'recommendations' => $recommendations,
            'alerts' => [
                'triggered' => !$isAligned,
                'type' => !$isAligned ? 'insight' : 'info',
                'priority' => !$isAligned ? 'medium' : 'low',
                'message' => $explanation
            ],
            'flags' => [],
            'staff_impact' => [
                'staff_blame_detected' => false,
                'note' => ''
            ],
            'explainability' => $explainability,
            'summary' => $summary,
            'sarcasm' => $result['sarcasm'] ?? ['detected' => false],
            'spam_probability' => $result['spam_probability'] ?? 0.0
        ];
    }

    /**
     * Analyze a review and save results to database
     */
    public function analyzeReview(ReviewNew $review, bool $forceReprocess = false): array
    {
        if ($review->is_ai_processed && !$forceReprocess) {
            return [
                'status' => 'already_processed',
                'sentiment_label' => $review->sentiment_label,
                'sentiment_score' => $review->sentiment_score ?? (float) config('ai.topics.intensity_mapping.default', 0.5),
                'emotion' => $review->emotion,
                'ai_confidence' => $review->ai_confidence ?? ((float) config('ai.insights.opportunities.preview.base_precision', 85.0) / 100),
                'is_abusive' => $review->is_abusive,
                'message' => 'Review already processed. Use --force flag to reprocess.'
            ];
        }

        $text = $review->raw_text ?? $review->comment ?? '';
        if (trim($text) === '') {
            return $this->analyzeReviewLocally($review);
        }

        try {
            $payload = $this->createPayloadFromReview($review);
            Log::debug('Review payload for OpenAI', [
                'review_id' => $review->id,
                'text_preview' => substr($payload['review_text'] ?? '', 0, 100),
                'text_length' => strlen($payload['review_text'] ?? '')
            ]);
            $businessId = $review->business_id;

            $enabledModules = $this->getBusinessAiModules($businessId);

            Log::debug('Analyzing review with modules', [
                'review_id' => $review->id,
                'business_id' => $businessId,
                'enabled_modules' => $enabledModules
            ]);

            $openAIResult = $this->processReviewWithOpenAI($payload, $enabledModules);
            $normalizedResult = $this->normalizeOpenAIResult($openAIResult, $review);

            $dbData = $this->convertForDatabase($normalizedResult, $review, $enabledModules);

            $review->fill($dbData);
            $this->ruleExecutionService->resetRuleOutcomes($review);
            $review->save();

            $realTimeRules = AiRule::where('business_id', $businessId)
                ->where('enabled', true)
                ->where('run_frequency', 'real_time')
                ->get();

            foreach ($realTimeRules as $rule) {
                $this->ruleExecutionService->executeRule($rule, [$review], $dbData);
            }

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

            throw $e;
        }
    }





    public function generateRecommendations(array $aiResult, array $enabledModules): array
    {
        $enabled = $this->moduleEnabled($enabledModules, 'recommendations')
            || $this->moduleEnabled($enabledModules, 'business_recommendations');

        if (!$enabled) {
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

        $sentimentScore = $this->normalizeSentimentScore($result['sentiment']['score'] ?? config('ai.topics.intensity_mapping.default', 0.5));
        $sentimentLabel = $this->normalizeSentimentLabel($result['sentiment']['label'] ?? null, $sentimentScore);

        $moderation = $result['moderation'] ?? [];
        $explainability = $result['explainability'] ?? [];

        return [
            'sentiment_score' => $sentimentScore,
            'sentiment_label' => $sentimentLabel,
            'emotion' => $result['emotion'] ?? ['primary' => 'neutral', 'intensity' => 'low'],

            // Correct key: prompt returns "moderation", not "flagging".
            'is_abusive' => (bool) ($moderation['is_abusive'] ?? false),
            'moderation_results' => $moderation,

            'is_ai_processed' => true,

            // Correct key: prompt returns explainability.confidence_score.
            'ai_confidence' => (float) ($explainability['confidence_score'] ?? (config('ai.insights.opportunities.preview.base_precision', 85.0) / 100)),

            'ai_processed_at' => now(),
            'ai_model' => Config::get('services.openai.model', 'gpt-4o-mini'),

            'rating_comment_mismatch' => (bool) ($mismatchInsights['has_mismatch'] ?? false),

            // Do not json_encode because ReviewNew casts this field as array.
            'mismatch_insights' => $mismatchInsights,

            'ai_insights' => $this->extractInsights($result, $enabledModules),
            'ai_recommendations' => $this->generateRecommendations($result, $enabledModules),

            'language' => $result['language']['detected'] ?? 'en',
            'summary' => $result['summary']['one_line'] ?? ($result['summary']['manager_summary'] ?? ''),
            'openai_raw_response' => $result,

            // Store the real staff/area data where rules currently look for it.
            'key_phrases' => $this->buildKeyPhrases($result),

            'topics' => $result['category_analysis'] ?? [],
            'service_analysis' => $result['category_analysis'] ?? [],

            'ai_suggestions' => $result['recommendations']['business_actions'] ?? [],
            'staff_suggestions' => $result['recommendations']['staff_actions'] ?? [],
            'review_type' => $review->review_type ?? ($review->is_voice_review ? 'voice' : 'text'),
        ];
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
     * Estimate token footprint per review based on enabled modules
     */
    public static function estimateTokensPerReview(array $moduleNames): int
    {
        $promptBase = 1800;
        $completionBase = 400;

        $promptCosts = [
            'category_analysis' => 220,
            'staff_intelligence' => 280,
            'service_unit_intelligence' => 80,
            'business_recommendations' => 260,
            'alerts' => 180,
            'sentiment_analysis' => 120,
            'emotion_detection' => 60,
            'abuse_detection' => 40,
            'explainability' => 0,
            'language_translation' => 0,
            'multi_branch' => 0,
            'rules_management' => 0,
        ];

        $completionCosts = [
            'category_analysis' => 350,
            'staff_intelligence' => 250,
            'service_unit_intelligence' => 80,
            'business_recommendations' => 300,
            'alerts' => 120,
            'sentiment_analysis' => 0,
            'emotion_detection' => 0,
            'abuse_detection' => 0,
            'explainability' => 120,
            'language_translation' => 80,
            'multi_branch' => 0,
            'rules_management' => 0,
        ];

        $promptTokens = $promptBase;
        $completionTokens = $completionBase;

        foreach ($moduleNames as $moduleName) {
            $promptTokens += $promptCosts[$moduleName] ?? 0;
            $completionTokens += $completionCosts[$moduleName] ?? 0;
        }

        return $promptTokens + $completionTokens;
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

        // Get estimation metrics
        $enabledModulesMap = $this->getBusinessAiModules($businessId);
        $activeModules = array_keys(array_filter($enabledModulesMap));
        $estimatedTokensPerReview = self::estimateTokensPerReview($activeModules);

        $business = \App\Models\Business::find($businessId);
        $tokenLimit = $business ? $business->openai_token_limit : -1;
        $estimatedReviewsLimit = $tokenLimit === -1 ? -1 : (int)floor($tokenLimit / $estimatedTokensPerReview);

        // Count of actually processed reviews during this period
        $processedReviewsCount = (int)($stats->total_requests ?? 0);
        $remainingReviewsCount = $estimatedReviewsLimit === -1 ? -1 : max(0, $estimatedReviewsLimit - $processedReviewsCount);

        return [
            'period' => $period,
            'total_prompt_tokens' => $stats->total_prompt_tokens ?? 0,
            'total_completion_tokens' => $stats->total_completion_tokens ?? 0,
            'total_tokens' => $stats->total_tokens ?? 0,
            'total_cost' => $stats->total_cost ?? 0,
            'total_requests' => $stats->total_requests ?? 0,
            'avg_tokens_per_request' => $stats->avg_tokens_per_request ?? 0,
            'estimated_tokens_per_review' => $estimatedTokensPerReview,
            'estimated_reviews_limit' => $estimatedReviewsLimit,
            'processed_reviews_count' => $processedReviewsCount,
            'remaining_reviews_count' => $remainingReviewsCount,
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
     * Local processing fallback for rating-only reviews (no comments)
     */
    public function analyzeReviewLocally(ReviewNew $review): array
    {
        $rating = $review->calculated_rating;
        
        $sentimentLabel = 'neutral';
        $sentimentConfidence = 0.5;
        $primaryEmotion = 'neutral';
        
        if ($rating >= 4.0) {
            $sentimentLabel = 'positive';
            $sentimentConfidence = 0.9;
            $primaryEmotion = 'satisfaction';
        } elseif ($rating <= 2.0) {
            $sentimentLabel = 'negative';
            $sentimentConfidence = 0.9;
            $primaryEmotion = 'frustration';
        }

        // Gather survey details to generate list of topics, positive aspects, and negative aspects
        $topics = [];
        $positiveAspects = [];
        $negativeAspects = [];
        $issues = [];

        if ($review->survey_id) {
            $values = $review->relationLoaded('value')
                ? $review->value
                : $review->value()->with(['question', 'tags'])->get();

            $starIds = $values->pluck('star_id')->filter()->unique()->values();
            $starValuesById = $starIds->isEmpty()
                ? collect()
                : \App\Models\Star::whereIn('id', $starIds)->pluck('value', 'id');

            foreach ($values as $value) {
                if ($value->question_id) {
                    $category = $value->question->category ?? 'General';
                    $topics[] = $category;

                    $val = $value->star_id ? ($starValuesById[$value->star_id] ?? 0) : 0;
                    if ($val >= 4) {
                        $positiveAspects[] = $value->question->question_text;
                    } elseif ($val <= 2) {
                        $negativeAspects[] = $value->question->question_text;
                        $issues[] = [
                            'category' => $category,
                            'severity' => $val <= 1 ? 'high' : 'medium'
                        ];
                    }
                }
                
                // Get selected tags / labels
                foreach ($value->tags as $tag) {
                    $positiveAspects[] = $tag->name;
                }
            }
        }

        $mockResult = [
            'language' => [
                'detected' => $review->language ?? 'en',
                'translated_text' => null
            ],
            'sentiment' => [
                'label' => $sentimentLabel,
                'confidence' => $sentimentConfidence
            ],
            'emotion' => [
                'primary' => $primaryEmotion,
                'intensity' => 'medium',
                'confidence' => 0.8
            ],
            'topics' => array_unique($topics),
            'positive_aspects' => array_unique($positiveAspects),
            'negative_aspects' => array_unique($negativeAspects),
            'issues' => $issues,
            'abusive_language' => [
                'detected' => false
            ],
            'sarcasm' => [
                'detected' => false
            ],
            'spam_probability' => 0.0,
            'summary' => "Customer selected ratings indicating a {$sentimentLabel} experience (Overall rating: {$rating}).",
            'recommendations' => []
        ];

        $normalizedResult = $this->normalizeOpenAIResult($mockResult, $review);
        $dbData = $this->convertForDatabase($normalizedResult, $review);
        $dbData['ai_model'] = 'local_auto_processor';
        
        $review->fill($dbData);
        $this->ruleExecutionService->resetRuleOutcomes($review);
        $review->save();
        
        Log::info('Review locally analyzed (rating-only)', [
            'review_id' => $review->id,
            'rating' => $rating,
            'sentiment' => $sentimentLabel
        ]);

        log_message([
            'event' => 'Local Review Analysis (No Comment)',
            'review_id' => $review->id,
            'rating' => $rating,
            'sentiment' => $sentimentLabel,
            'results' => $mockResult
        ], 'local_rules.log');
        
        return array_merge($dbData, [
            'status' => 'success',
            'message' => 'Processed rating-only review locally successfully.'
        ]);
    }

    /**
     * Generate rolling AI insight by merging previous and latest insights
     */
    public function generateRollingInsight(?array $previousInsight, array $latestInsight, array $currentMetrics = []): array
    {
        $apiKey = \config('services.openai.api_key');
        $model = \config('services.openai.model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        $systemPrompt = "You are an AI that updates an existing structured business intelligence report.\n\n"
            . "Do NOT regenerate everything from scratch.\n"
            . "The first input represents the current business intelligence state (JSON).\n"
            . "The second input contains ONLY new reviews received since the last update. Each review includes: rating, sentiment, emotion, topics, positive_aspects, negative_aspects, issues (with categories and severity), summary, confidence, and moderation flags.\n"
            . "The third input is a batch summary with pre-calculated statistics (average rating, sentiment distribution, issue frequency counts).\n"
            . "Update the business intelligence state using ALL three inputs.\n"
            . "- Use issue frequency counts from the batch summary to detect recurring issues rather than guessing from summaries alone.\n"
            . "- Use the provided business metrics and batch statistics to ground your narrative with actual numbers.\n"
            . "- When updating the trend direction, consider both the previous review count and the new batch size. Avoid drastic trend changes caused by only a small number of new reviews. Trend should evolve gradually unless there is overwhelming evidence of change.\n"
            . "- If trends are genuinely changing, explain why in the summary and update the trend direction.\n"
            . "- If previous weaknesses are improving based on new data, reduce their importance or remove them.\n"
            . "- If new recurring strengths appear in multiple reviews, include them.\n"
            . "- If recommendations should change based on new patterns, update them.\n"
            . "- Preserve useful historical context.\n"
            . "Return ONLY a complete, valid JSON object matching the following structure:\n"
            . "{\n"
            . "  \"summary\": \"Executive summary of business performance (evolve the narrative naturally, incorporating the current metrics where helpful)\",\n"
            . "  \"strengths\": [\"list of top strengths/positive aspects\"],\n"
            . "  \"weaknesses\": [\"list of top weaknesses/issues\"],\n"
            . "  \"top_topics\": [\"list of top topics\"],\n"
            . "  \"trend\": \"Improving|Declining|Stable\",\n"
            . "  \"recommendations\": [\"actionable recommendations\"],\n"
            . "  \"confidence\": 0.0 to 1.0\n"
            . "}";

        $defaultPreviousInsight = [
            'summary' => 'No previous AI summary generated yet.',
            'strengths' => [],
            'weaknesses' => [],
            'top_topics' => [],
            'trend' => 'Stable',
            'recommendations' => [],
            'confidence' => 1.0,
        ];

        $userMessage = "Current Business Insight State:\n"
            . "<previous_insight>\n"
            . json_encode($previousInsight ?: $defaultPreviousInsight, JSON_PRETTY_PRINT)
            . "\n</previous_insight>"
            . "\n\nNew Reviews:\n"
            . "<new_reviews>\n"
            . json_encode($latestInsight, JSON_PRETTY_PRINT)
            . "\n</new_reviews>";

        if (!empty($currentMetrics)) {
            $userMessage .= "\n\nCurrent Business Metrics (Calculated locally from backend, use these for context in summary):\n"
                . json_encode($currentMetrics, JSON_PRETTY_PRINT);
        }

        $userMessage .= "\n\nUpdate the business intelligence state based on the new reviews and return only the updated JSON matching the schema.";

        $requestPayload = [
            'model' => $model,
            'temperature' => 0.2,
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
        ];

        try {
            log_message([
                'type' => 'REQUEST',
                'action' => 'generateRollingInsight',
                'payload' => $requestPayload
            ], 'openai_calls.log');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', $requestPayload);

            if ($response->failed()) {
                log_message([
                    'type' => 'RESPONSE_ERROR',
                    'action' => 'generateRollingInsight',
                    'status' => $response->status(),
                    'error' => $response->body()
                ], 'openai_calls.log');

                Log::error('OpenAI API failed during rolling insight generation', [
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $data = $response->json();

            log_message([
                'type' => 'RESPONSE_SUCCESS',
                'action' => 'generateRollingInsight',
                'status' => $response->status(),
                'response' => $data
            ], 'openai_calls.log');

            $content = $data['choices'][0]['message']['content'] ?? '{}';

            Log::info('Rolling insight generated from OpenAI', [
                'content' => $content
            ]);

            // Save token usage for the rolling insight call
            if (!empty($data['usage'])) {
                try {
                    $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
                    $completionTokens = $data['usage']['completion_tokens'] ?? 0;
                    \App\Models\OpenAITokenUsage::create([
                        'business_id' => null,
                        'review_id' => null,
                        'branch_id' => null,
                        'model' => $data['model'] ?? $model,
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_tokens' => $data['usage']['total_tokens'] ?? 0,
                        'estimated_cost' => \App\Models\OpenAITokenUsage::calculateCost(
                            $data['model'] ?? $model,
                            $promptTokens,
                            $completionTokens
                        ),
                        'metadata' => ['action' => 'generateRollingInsight'],
                        'created_at' => now(),
                    ]);
                } catch (\Exception $tokenEx) {
                    Log::warning('Failed to save rolling insight token usage', ['error' => $tokenEx->getMessage()]);
                }
            }

            return json_decode($content, true) ?: [];

        } catch (\Exception $e) {
            Log::error('Failed to generate rolling insight', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

