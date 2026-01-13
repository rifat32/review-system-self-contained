<?php
// app/Helpers/RuleExplanationHelper.php

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
            $requestBody = self::buildOpenAIRequest($ruleData);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', $requestBody);

            if (!$response->successful()) {
                Log::error('OpenAI API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request' => $requestBody
                ]);
                return null;
            }

            $responseData = $response->json();

            Log::info('OpenAI API response received', [
                'has_content' => isset($responseData['content']),
                'content_count' => count($responseData['content'] ?? [])
            ]);

            return self::parseOpenAIResponse($responseData);

        } catch (\Exception $e) {
            Log::error('Rule explanation generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 1000,
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => self::getSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => self::buildUserPrompt($ruleData)
                ]
            ]
        ];
    }

    /**
     * Get system prompt for OpenAI
     */
    private static function getSystemPrompt(): string
    {
        return 'You are an AI assistant that explains business rules in simple, non-technical language for business owners. ' .
            'You must respond ONLY with valid JSON matching this exact structure: ' .
            '{"short_explanation": "one sentence", "detailed_explanation": "2-3 sentences", "why_it_matters": "business impact"}. ' .
            'Do not include any markdown formatting, code blocks, or additional text outside the JSON.';
    }

    /**
     * Build user prompt with rule details
     */
    private static function buildUserPrompt(array $ruleData): string
    {
        $businessType = $ruleData['business_type'] ?? 'Business';
        $ruleName = $ruleData['rule_name'] ?? 'Unnamed Rule';
        $category = $ruleData['category'] ?? 'General';
        $priority = $ruleData['priority'] ?? 'medium';
        $mainCategory = $ruleData['main_category'] ?? '';
        $subCategory = $ruleData['sub_category'] ?? '';
        $severity = $ruleData['severity'] ?? 'medium';
        $frequency = $ruleData['frequency'] ?? 0;

        $conditions = $ruleData['conditions'] ?? [];
        $actions = $ruleData['actions'] ?? [];

        // Build a more descriptive prompt
        $prompt = [
            'task' => 'Generate a clear, business-friendly explanation of this AI rule',
            'context' => [
                'business_type' => $businessType,
                'industry_context' => "This rule applies to a {$businessType} business"
            ],
            'rule_details' => [
                'name' => $ruleName,
                'category' => $category,
                'main_category' => $mainCategory,
                'sub_category' => $subCategory,
                'priority' => $priority,
                'severity' => $severity,
                'detection_frequency' => "{$frequency} times in recent feedback",
                'conditions' => $conditions,
                'actions' => $actions
            ],
            'requirements' => [
                'tone' => 'friendly and professional',
                'language' => 'simple, non-technical',
                'focus' => 'business value and actionable insights'
            ],
            'output_format' => [
                'short_explanation' => 'One clear sentence (10-15 words) for tooltips and quick reference',
                'detailed_explanation' => '2-3 sentences explaining what the rule does and how it works',
                'why_it_matters' => '1-2 sentences explaining the business impact and why this rule is valuable'
            ],
            'examples' => [
                'good_short' => 'Flags reviews where customers give high ratings but mention problems',
                'good_detailed' => 'This rule analyzes reviews to find cases where customers rate highly but describe negative experiences in their comments. It helps identify hidden dissatisfaction that might be missed by only looking at star ratings.',
                'good_why' => 'Not all unhappy customers leave low ratings. Catching mixed feedback early lets you address issues before they lead to lost business or negative word-of-mouth.'
            ]
        ];

        return json_encode($prompt, JSON_PRETTY_PRINT);
    }

    /**
     * Parse OpenAI response and extract explanations
     */
    private static function parseOpenAIResponse(array $responseData): ?array
    {
        try {
            // Extract text content from Claude's response format
            $textContent = self::extractTextFromResponse($responseData);

            if (empty($textContent)) {
                Log::warning('No text content in OpenAI response', [
                    'response_structure' => array_keys($responseData)
                ]);
                return null;
            }

            Log::info('Extracted text from OpenAI', [
                'text_length' => strlen($textContent),
                'text_preview' => substr($textContent, 0, 200)
            ]);

            // Try to parse as JSON
            $parsed = self::extractJsonFromText($textContent);

            if (!$parsed) {
                Log::warning('Failed to parse JSON from OpenAI response', [
                    'text' => $textContent
                ]);
                return null;
            }

            // Validate required fields
            if (
                !isset($parsed['short_explanation']) ||
                !isset($parsed['detailed_explanation']) ||
                !isset($parsed['why_it_matters'])
            ) {

                Log::warning('OpenAI response missing required fields', [
                    'parsed' => $parsed,
                    'has_short' => isset($parsed['short_explanation']),
                    'has_detailed' => isset($parsed['detailed_explanation']),
                    'has_why' => isset($parsed['why_it_matters'])
                ]);
                return null;
            }

            // Clean and validate the explanations
            $explanations = [
                'short_explanation' => self::cleanExplanationText($parsed['short_explanation']),
                'detailed_explanation' => self::cleanExplanationText($parsed['detailed_explanation']),
                'why_it_matters' => self::cleanExplanationText($parsed['why_it_matters'])
            ];

            // Ensure none are empty after cleaning
            foreach ($explanations as $key => $value) {
                if (empty($value)) {
                    Log::warning("Empty explanation after cleaning: {$key}");
                    return null;
                }
            }

            Log::info('Successfully parsed OpenAI explanations', [
                'short_length' => strlen($explanations['short_explanation']),
                'detailed_length' => strlen($explanations['detailed_explanation']),
                'why_length' => strlen($explanations['why_it_matters'])
            ]);

            return $explanations;

        } catch (\Exception $e) {
            Log::error('Failed to parse OpenAI response', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'response' => $responseData
            ]);
            return null;
        }
    }

    /**
     * Extract text content from Claude's response structure
     */
    private static function extractTextFromResponse(array $responseData): string
    {
        $textParts = [];

        // Claude API returns content as an array of blocks
        if (isset($responseData['content']) && is_array($responseData['content'])) {
            foreach ($responseData['content'] as $block) {
                if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                    $textParts[] = $block['text'];
                }
            }
        }

        // Fallback: check if there's direct text field
        if (empty($textParts) && isset($responseData['text'])) {
            $textParts[] = $responseData['text'];
        }

        return implode("\n", $textParts);
    }

    /**
     * Extract and parse JSON from text (handles markdown code blocks)
     */
    private static function extractJsonFromText(string $text): ?array
    {
        // Remove markdown code blocks if present
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        // Try direct JSON decode
        $parsed = json_decode($text, true);
        if ($parsed !== null && json_last_error() === JSON_ERROR_NONE) {
            return $parsed;
        }

        // Try to find JSON within the text
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
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove any remaining markdown
        $text = preg_replace('/[*_`]/', '', $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Generate fallback explanations when OpenAI fails
     */
    public static function generateFallbackExplanations(array $ruleData): array
    {
        $category = $ruleData['category'] ?? 'general';
        $mainCategory = $ruleData['main_category'] ?? '';
        $subCategory = $ruleData['sub_category'] ?? '';
        $priority = $ruleData['priority'] ?? 'medium';
        $frequency = $ruleData['frequency'] ?? 0;

        $templates = self::getFallbackTemplates();

        $key = strtolower($category);
        if (isset($templates[$key])) {
            return [
                'short_explanation' => $templates[$key]['short'],
                'detailed_explanation' => str_replace(
                    ['{main_category}', '{sub_category}', '{priority}', '{frequency}'],
                    [$mainCategory, $subCategory, $priority, $frequency],
                    $templates[$key]['detailed']
                ),
                'why_it_matters' => $templates[$key]['why']
            ];
        }

        // Generic fallback
        return [
            'short_explanation' => "Flags reviews matching specific {$category} criteria.",
            'detailed_explanation' => "This rule analyzes customer feedback related to {$mainCategory} and identifies patterns that require attention. Detected {$frequency} times in recent reviews.",
            'why_it_matters' => "Identifying {$category} issues early helps maintain quality and customer satisfaction."
        ];
    }

    /**
     * Get fallback explanation templates by category
     */
    private static function getFallbackTemplates(): array
    {
        return [
            'staff' => [
                'short' => 'Flags reviews mentioning staff performance issues.',
                'detailed' => 'This rule identifies feedback related to {main_category} in staff interactions. It helps track patterns in employee performance and has been detected {frequency} times.',
                'why' => 'Addressing staff issues quickly improves service quality and prevents recurring complaints.'
            ],
            'area' => [
                'short' => 'Detects location or facility-related concerns.',
                'detailed' => 'This rule monitors feedback about {main_category} in specific areas or facilities, helping identify maintenance or operational needs. Pattern detected {frequency} times.',
                'why' => 'Keeping facilities well-maintained directly impacts customer experience and safety.'
            ],
            'trend' => [
                'short' => 'Tracks recurring patterns in customer feedback.',
                'detailed' => 'This rule identifies when {main_category} issues appear repeatedly over time, signaling systematic problems. Seen {frequency} times in recent reviews.',
                'why' => 'Catching trends early allows proactive fixes before issues escalate.'
            ],
            'quality' => [
                'short' => 'Monitors product or service quality concerns.',
                'detailed' => 'This rule flags feedback related to {main_category} quality, helping maintain standards. Detected {frequency} times recently.',
                'why' => 'Quality consistency is essential for customer retention and brand reputation.'
            ]
        ];
    }

    /**
     * Regenerate explanations for existing rule
     */
    public static function regenerateForRule($rule): ?array
    {
        $ruleData = [
            'business_type' => $rule->business_type ?? 'Business',
            'rule_name' => $rule->rule_name,
            'category' => $rule->category,
            'priority' => $rule->priority,
            'conditions' => is_string($rule->conditions)
                ? json_decode($rule->conditions, true)
                : $rule->conditions,
            'actions' => is_string($rule->actions)
                ? json_decode($rule->actions, true)
                : $rule->actions,
            'main_category' => $rule->conditions['category_match']['main_category'] ?? '',
            'sub_category' => $rule->conditions['category_match']['sub_category'] ?? '',
            'frequency' => 0 // Unknown for existing rules
        ];

        // Try OpenAI first
        $explanations = self::generateExplanations($ruleData);

        // Fallback if OpenAI fails
        if (!$explanations) {
            Log::info('Using fallback explanations for rule regeneration', [
                'rule_id' => $rule->rule_id
            ]);
            $explanations = self::generateFallbackExplanations($ruleData);
        }

        return $explanations;
    }

    /**
     * Check if explanations need regeneration
     */
    public static function needsRegeneration($rule): bool
    {
        // Needs regeneration if any explanation is missing
        if (
            empty($rule->short_explanation) ||
            empty($rule->detailed_explanation) ||
            empty($rule->why_it_matters)
        ) {
            return true;
        }

        // Check if rule logic changed (version increment without explanation update)
        if (
            $rule->version > 1 &&
            $rule->updated_at > ($rule->explanation_generated_at ?? $rule->created_at)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Batch generate explanations for multiple rules
     */
    public static function batchGenerateExplanations(array $rules): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'fallback' => 0,
            'details' => []
        ];

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
                    $results['details'][] = [
                        'rule_id' => $rule->rule_id,
                        'status' => 'success'
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'rule_id' => $rule->rule_id,
                        'status' => 'failed'
                    ];
                }

            } catch (\Exception $e) {
                Log::error('Batch explanation generation failed for rule', [
                    'rule_id' => $rule->rule_id,
                    'error' => $e->getMessage()
                ]);

                $results['failed']++;
                $results['details'][] = [
                    'rule_id' => $rule->rule_id,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}