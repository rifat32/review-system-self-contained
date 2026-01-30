<?php

namespace App\Services\Rule;

use App\Models\ReviewNew;

class ConditionBuilderService
{
    // ==================== VALIDATION ====================

    /**
     * Validate condition structure
     * 
     * @param array $conditions Condition tree to validate
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateConditionTree(array $conditions): array
    {
        $errors = [];

        foreach ($conditions as $index => $condition) {
            // Check for nested group
            if (isset($condition['group'])) {
                $nestedErrors = self::validateConditionTree($condition['group']);
                $errors = array_merge($errors, $nestedErrors);
                continue;
            }

            // Validate source
            if (isset($condition['source'])) {
                $validSources = ['Comment', 'Rating', 'Staff', 'Area', 'Emotion', 'Trend'];
                if (!in_array($condition['source'], $validSources)) {
                    $errors[] = "Condition at index $index has invalid source: {$condition['source']}";
                }
            }

            // Validate condition type
            if (!isset($condition['type'])) {
                $errors[] = "Condition at index $index is missing 'type' field";
                continue;
            }

            $validTypes = ['sentiment', 'rating', 'keyword', 'staff_mention', 'area_mention', 'emotion', 'service_type', 'frequency', 'trend_direction'];
            if (!in_array($condition['type'], $validTypes)) {
                $errors[] = "Condition at index $index has invalid type: {$condition['type']}";
            }

            // Validate operator
            if (!isset($condition['operator'])) {
                $errors[] = "Condition at index $index is missing 'operator' field";
                continue;
            }

            $validOperators = ['equals', 'contains', 'greater_than', 'less_than', 'between', 'regex', 'not_equals', 'starts_with', 'ends_with'];
            if (!in_array($condition['operator'], $validOperators)) {
                $errors[] = "Condition at index $index has invalid operator: {$condition['operator']}";
            }

            // Validate value exists
            if (!isset($condition['value']) && !in_array($condition['type'], ['staff_mention'])) {
                $errors[] = "Condition at index $index is missing 'value' field";
            }

            // Validate 'between' operator has array value with 2 elements
            if ($condition['operator'] === 'between') {
                if (!is_array($condition['value']) || count($condition['value']) !== 2) {
                    $errors[] = "Condition at index $index with 'between' operator must have array value with 2 elements";
                }
            }
        }

        return $errors;
    }

    // ==================== EVALUATION ====================

    /**
     * Evaluate condition tree against review data
     * 
     * @param array $conditions Condition tree to evaluate
     * @param ReviewNew $review Review to check against
     * @param array $aiData AI analysis data
     * @param string $logic Logic operator (AND or OR)
     * @return bool True if conditions match
     */
    public static function evaluateConditions($conditionData, ReviewNew $review, array $aiData, string $defaultLogic = 'AND'): bool
    {
        // Recursively decode if it's a double-encoded string
        while (is_string($conditionData)) {
            $decoded = json_decode($conditionData, true);
            if (json_last_error() !== JSON_ERROR_NONE || $decoded === $conditionData) {
                break;
            }
            $conditionData = $decoded;
        }

        if (!is_array($conditionData)) {
            return false;
        }

        // Handle both simple array of conditions and complex [logic => ..., conditions => ...] structure
        if (isset($conditionData['logic']) && isset($conditionData['conditions'])) {
            $logic = $conditionData['logic'];
            $conditions = $conditionData['conditions'];
        } else {
            $logic = $defaultLogic;
            $conditions = $conditionData;
        }

        if (empty($conditions)) {
            return true;
        }

        $results = [];

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if (isset($condition['group']) || (isset($condition['logic']) && isset($condition['conditions']))) {
                // Evaluate nested group
                $nestedConditions = $condition['group'] ?? $condition;
                $results[] = self::evaluateConditions($nestedConditions, $review, $aiData);
            } else {
                // Evaluate single condition
                $results[] = self::evaluateSingleCondition($condition, $review, $aiData);
            }
        }

        // Apply logic operator
        $logic = strtoupper($logic);
        if ($logic === 'OR') {
            return in_array(true, $results, true);
        } else {
            // Default to AND
            return !in_array(false, $results, true);
        }
    }

    /**
     * Evaluate single condition
     * 
     * @param array $condition Single condition to evaluate
     * @param ReviewNew $review Review to check against
     * @param array $aiData AI analysis data
     * @return bool True if condition matches
     */
    private static function evaluateSingleCondition(array $condition, ReviewNew $review, array $aiData): bool
    {
        $source = $condition['source'] ?? null;
        $type = $condition['type'] ?? $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        // Route by source if available, otherwise fallback to type
        if ($source === 'Rating' || $type === 'rating') {
            $rating = $review->calculated_rating ?? 0;
            return self::matchNumeric($rating, $operator, $value);
        }

        if ($source === 'Comment' || in_array($type, ['sentiment', 'keyword', 'emotion'])) {
            if ($type === 'sentiment') {
                // OpenAI returns sentiment in different formats depending on module
                $sentiment = $aiData['sentiment']['label'] ?? $aiData['overall_sentiment'] ?? $aiData['sentiment'] ?? 'neutral';
                if (is_array($sentiment)) {
                    $sentiment = $sentiment['label'] ?? 'neutral';
                }
                return self::matchSentiment($sentiment, $operator, $value);
            }
            if ($type === 'keyword') {
                $text = $review->comment ?? $review->raw_text ?? '';
                // Also check translated text if available
                if (isset($aiData['language']['translated_text'])) {
                    $text .= " " . $aiData['language']['translated_text'];
                }
                return self::matchText($text, $operator, $value);
            }
            if ($type === 'emotion' || $type === 'intensity') {
                // Handle complex emotion intensity matching
                $intensityStr = $aiData['emotion']['intensity'] ?? 'low';
                $intensityMapping = config('ai.topics.intensity_mapping', []);
                $intensityVal = $intensityMapping[strtolower((string)$intensityStr)] ?? ($intensityMapping['default'] ?? 0.5);

                // If it's a numeric comparison for intensity
                if ($type === 'intensity' || is_numeric($value)) {
                    return self::matchNumeric($intensityVal, $operator, $value);
                }

                // If it's a primary emotion match
                $primaryEmotion = $aiData['emotion']['primary'] ?? 'neutral';
                return self::matchSentiment($primaryEmotion, $operator, $value);
            }
        }

        if ($source === 'Trend' || in_array($type, ['frequency', 'trend_direction'])) {
            $trendData = $aiData['trend_data'] ?? [];
            if ($type === 'frequency') {
                return self::matchNumeric($trendData['frequency'] ?? 0, $operator, $value);
            }
            if ($type === 'trend_direction') {
                return ($trendData['direction'] ?? 'steady') === $value;
            }
        }

        if ($source === 'Staff' || $type === 'staff_mention') {
            // Check staff intelligence from OpenAI
            $staffInfo = $aiData['staff_intelligence'] ?? null;
            if ($staffInfo && isset($staffInfo['mentioned_explicitly']) && $staffInfo['mentioned_explicitly']) {
                if ($value && isset($staffInfo['staff_name'])) {
                    return self::matchText($staffInfo['staff_name'], 'contains', $value);
                }
                return true;
            }

            // Fallback for older staff mentions format
            $staffMentions = $aiData['staff_mentions'] ?? [];
            if ($value) {
                return in_array($value, array_column((array)$staffMentions, 'name'));
            }
            return !empty($staffMentions);
        }

        if ($source === 'Area' || $type === 'area_mention') {
            // Check area insights
            $areaInsights = $aiData['area_insights'] ?? [];
            if (!empty($areaInsights)) {
                if ($value) {
                    foreach ($areaInsights as $area) {
                        if (self::matchText($area['area_name'] ?? '', 'contains', $value)) return true;
                    }
                    return false;
                }
                return true;
            }

            // Fallback
            $areaMentions = $aiData['areas'] ?? [];
            if (is_array($areaMentions)) {
                return in_array($value, array_column($areaMentions, 'name'));
            }
            return in_array($value, (array)$areaMentions);
        }

        // Fallback for other types
        switch ($type) {
            case 'service_type':
                $serviceInfo = $aiData['service_unit_intelligence'] ?? null;
                if ($serviceInfo && isset($serviceInfo['unit_type'])) {
                    return self::matchText($serviceInfo['unit_type'], 'equals', $value);
                }
                $serviceTypes = $aiData['service_types'] ?? [];
                return in_array($value, $serviceTypes);

            default:
                return false;
        }
    }

    // ==================== MATCHERS ====================

    /**
     * Match sentiment condition
     */
    private static function matchSentiment(?string $sentiment, string $operator, $value): bool
    {
        $sentiment = strtolower($sentiment ?? 'neutral');
        $value = strtolower((string)$value);

        switch ($operator) {
            case 'equals':
            case 'eq':
                return $sentiment === $value;
            case 'not_equals':
            case 'neq':
                return $sentiment !== $value;
            default:
                return false;
        }
    }

    /**
     * Match numeric condition
     */
    private static function matchNumeric($actual, string $operator, $value): bool
    {
        $actual = (float)$actual;
        $value = is_array($value) ? array_map('floatval', $value) : (float)$value;

        $epsilon = config('ai.topics.numeric_epsilon') ?? 0.01;

        switch ($operator) {
            case 'equals':
            case 'eq':
                return abs($actual - $value) < $epsilon;
            case 'not_equals':
            case 'neq':
                return abs($actual - $value) >= $epsilon;
            case 'greater_than':
            case 'gt':
                return $actual > $value;
            case 'less_than':
            case 'lt':
                return $actual < $value;
            case 'greater_than_or_equal':
            case 'gte':
                return $actual >= $value;
            case 'less_than_or_equal':
            case 'lte':
                return $actual <= $value;
            case 'between':
                return is_array($value) && $actual >= $value[0] && $actual <= $value[1];
            default:
                return false;
        }
    }

    /**
     * Match text condition
     */
    private static function matchText(?string $text, string $operator, $value): bool
    {
        $text = strtolower($text ?? '');
        $value = strtolower((string)$value);

        switch ($operator) {
            case 'contains':
                return str_contains($text, $value);
            case 'equals':
            case 'eq':
                return $text === $value;
            case 'not_equals':
            case 'neq':
                return $text !== $value;
            case 'starts_with':
                return str_starts_with($text, $value);
            case 'ends_with':
                return str_ends_with($text, $value);
            case 'regex':
                return @preg_match($value, $text) === 1;
            default:
                return false;
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get supported condition types
     */
    public static function getSupportedTypes(): array
    {
        return [
            'sentiment' => [
                'label' => 'Sentiment',
                'operators' => ['equals', 'not_equals'],
                'values' => ['positive', 'negative', 'neutral', 'very_positive', 'very_negative']
            ],
            'rating' => [
                'label' => 'Star Rating',
                'operators' => ['equals', 'not_equals', 'greater_than', 'less_than', 'between'],
                'value_type' => 'number'
            ],
            'keyword' => [
                'label' => 'Comment Keyword',
                'operators' => ['contains', 'equals', 'not_equals', 'starts_with', 'ends_with', 'regex'],
                'value_type' => 'text'
            ],
            'staff_mention' => [
                'label' => 'Staff Mentioned',
                'operators' => ['equals'],
                'value_type' => 'staff_list'
            ],
            'area_mention' => [
                'label' => 'Business Area',
                'operators' => ['equals'],
                'value_type' => 'area_list'
            ],
            'emotion' => [
                'label' => 'Emotion Detected',
                'operators' => ['equals'],
                'values' => ['joy', 'anger', 'frustration', 'satisfaction', 'disappointment'],
                'has_threshold' => true
            ],
            'service_type' => [
                'label' => 'Service Type',
                'operators' => ['equals'],
                'value_type' => 'service_list'
            ]
        ];
    }

    /**
     * Format condition for display
     */
    public static function formatCondition(array $condition): string
    {
        $type = $condition['type'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? '';

        $operatorLabels = [
            'equals' => 'is',
            'not_equals' => 'is not',
            'greater_than' => 'is greater than',
            'less_than' => 'is less than',
            'between' => 'is between',
            'contains' => 'contains',
            'starts_with' => 'starts with',
            'ends_with' => 'ends with',
            'regex' => 'matches pattern'
        ];

        $operatorLabel = $operatorLabels[$operator] ?? $operator;

        if ($operator === 'between' && is_array($value)) {
            $value = "{$value[0]} and {$value[1]}";
        }

        return ucfirst($type) . " {$operatorLabel} \"{$value}\"";
    }
}
