<?php
// app/Services/Rule/RuleExplanationService.php

namespace App\Services\Rule;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RuleExplanationService
{
    /**
     * Generate AI explanations for a rule using OpenAI
     */
    public static function generateExplanations(array $ruleData): ?array
    {
        try {
            $apiKey = config('services.openai.api_key');
            if (!$apiKey) {
                Log::error('OpenAI API key not configured');
                return null;
            }

            $requestBody = self::buildOpenAIRequest($ruleData);

            $response = Http::timeout(config('services.openai.timeout', 30))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', $requestBody);

            if (!$response->successful()) {
                Log::error('OpenAI API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $responseData = $response->json();

            Log::info('OpenAI API response received', [
                'model' => $responseData['model'] ?? 'unknown',
                'usage' => $responseData['usage'] ?? []
            ]);

            return self::parseOpenAIResponse($responseData);
        } catch (\Exception $e) {
            Log::error('Rule explanation generation failed', [
                'error' => $e->getMessage(),
                'rule_data' => $ruleData
            ]);
            return null;
        }
    }

    /**
     * Build complete OpenAI API request body
     */
    private static function buildOpenAIRequest(array $ruleData): array
    {
        return [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => self::getSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => self::buildUserPrompt($ruleData)
                ]
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ];
    }

    /**
     * Get system prompt for OpenAI
     */
    private static function getSystemPrompt(): string
    {
        return "You are an expert product analyst. Your task is to explain business rules in clear, simple language for non-technical business owners. Do not include technical jargon. Explain what the rule does, why it matters, and when it triggers. You must respond with a JSON object matching this structure: {\"short_explanation\": \"...\", \"detailed_explanation\": \"...\", \"why_it_matters\": \"...\", \"when_it_triggers\": \"...\"}";
    }

    /**
     * Build user prompt with rule details
     */
    private static function buildUserPrompt(array $ruleData): string
    {
        $payload = [
            'rule_id' => $ruleData['rule_id'] ?? 'NEW_RULE',
            'rule_name' => $ruleData['rule_name'] ?? 'Unnamed Rule',
            'rule_type' => $ruleData['rule_type'] ?? (isset($ruleData['conditions']['category_match']) ? 'comment_based' : 'rating_based'),
            'conditions' => $ruleData['conditions'] ?? [],
            'actions' => $ruleData['actions'] ?? [],
            'confidence_threshold' => $ruleData['confidence_threshold'] ?? 0.7
        ];

        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    /**
     * Parse OpenAI response and extract explanations
     */
    private static function parseOpenAIResponse(array $responseData): ?array
    {
        try {
            $content = $responseData['choices'][0]['message']['content'] ?? '';
            if (empty($content)) {
                Log::warning('No content in OpenAI response');
                return null;
            }

            Log::info('Received content from OpenAI', [
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 100)
            ]);

            $parsed = json_decode($content, true);
            if (!$parsed) {
                // Try to extract JSON if it's wrapped in text
                $parsed = self::extractJsonFromText($content);
            }

            if (!$parsed) {
                Log::warning('Failed to parse JSON from OpenAI response', ['content' => $content]);
                return null;
            }

            // Validate required fields from proposal
            $required = ['short_explanation', 'detailed_explanation', 'why_it_matters', 'when_it_triggers'];
            foreach ($required as $field) {
                if (!isset($parsed[$field])) {
                    Log::warning("OpenAI response missing required field: {$field}", ['parsed' => $parsed]);
                    return null;
                }
                $parsed[$field] = self::cleanExplanationText($parsed[$field]);
            }

            return $parsed;
        } catch (\Exception $e) {
            Log::error('Failed to parse OpenAI response', [
                'error' => $e->getMessage(),
                'response' => $responseData
            ]);
            return null;
        }
    }

    /**
     * Extract and parse JSON from text (handles markdown code blocks)
     */
    private static function extractJsonFromText(string $text): ?array
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed !== null && json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }
        return null;
    }

    /**
     * Clean explanation text
     */
    private static function cleanExplanationText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[*_`]/', '', $text);
        return trim($text);
    }

    /**
     * Generate fallback explanations when OpenAI fails
     */
    public static function generateFallbackExplanations(array $ruleData): array
    {
        $category = $ruleData['category'] ?? 'general';
        $ruleName = $ruleData['rule_name'] ?? 'Business Rule';

        return [
            'short_explanation' => "AI Rule: {$ruleName}",
            'detailed_explanation' => "This rule monitors reviews for patterns related to {$category}.",
            'why_it_matters' => "Maintaining high standards in {$category} is crucial for customer satisfaction.",
            'when_it_triggers' => "This rule triggers when specific criteria matching the {$category} category are met in customer feedback."
        ];
    }

    /**
     * Regenerate explanations for existing rule
     */
    public static function regenerateForRule($rule): ?array
    {
        $ruleData = [
            'rule_id' => $rule->rule_id,
            'rule_name' => $rule->rule_name,
            'conditions' => is_string($rule->conditions) ? json_decode($rule->conditions, true) : $rule->conditions,
            'actions' => is_string($rule->actions) ? json_decode($rule->actions, true) : $rule->actions,
            'category' => $rule->category,
        ];

        $explanations = self::generateExplanations($ruleData);

        if (!$explanations) {
            Log::info('Using fallback explanations for rule regeneration', ['rule_id' => $rule->rule_id]);
            $explanations = self::generateFallbackExplanations($ruleData);
        }

        return $explanations;
    }

    /**
     * Check if explanations need regeneration
     */
    public static function needsRegeneration($rule): bool
    {
        return !$rule->hasExplanations();
    }

    /**
     * Batch generate explanations for multiple rules
     */
    public static function batchGenerateExplanations(array $rules): array
    {
        $results = ['success' => 0, 'failed' => 0];

        foreach ($rules as $rule) {
            try {
                $explanations = self::regenerateForRule($rule);

                if ($explanations) {
                    $rule->update([
                        'short_explanation' => $explanations['short_explanation'],
                        'detailed_explanation' => $explanations['detailed_explanation'],
                        'why_it_matters' => $explanations['why_it_matters'],
                        'explanation_generated_at' => now()
                    ]);
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                Log::error('Batch explanation generation failed', ['rule_id' => $rule->rule_id, 'error' => $e->getMessage()]);
                $results['failed']++;
            }
        }

        return $results;
    }
}
