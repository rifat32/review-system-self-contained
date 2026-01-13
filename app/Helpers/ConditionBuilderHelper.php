<?php

namespace App\Helpers;

use App\Models\ReviewNew;

class ConditionBuilderHelper
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

            // Validate condition type
            if (!isset($condition['type'])) {
                $errors[] = "Condition at index $index is missing 'type' field";
                continue;
            }

            $validTypes = ['sentiment', 'rating', 'keyword', 'staff_mention', 'area_mention', 'emotion', 'service_type'];
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
    public static function evaluateConditions(array $conditions, ReviewNew $review, array $aiData, string $logic = 'AND'): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $results = [];

        foreach ($conditions as $condition) {
            if (isset($condition['group'])) {
                // Evaluate nested group
                $groupLogic = $condition['logic'] ?? 'AND';
                $results[] = self::evaluateConditions($condition['group'], $review, $aiData, $groupLogic);
            } else {
                // Evaluate single condition
                $results[] = self::evaluateSingleCondition($condition, $review, $aiData);
            }
        }

        // Apply logic operator
        if ($logic === 'AND') {
            return !in_array(false, $results, true);
        } else { // OR
            return in_array(true, $results, true);
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
        $type = $condition['type'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        switch ($type) {
            case 'sentiment':
                $sentiment = $aiData['sentiment'] ?? 'neutral';
                return self::matchSentiment($sentiment, $operator, $value);

            case 'rating':
                return self::matchNumeric($review->rating, $operator, $value);

            case 'keyword':
                return self::matchText($review->comment, $operator, $value);

            case 'staff_mention':
                $staffMentions = $aiData['staff_mentions'] ?? [];
                if ($value) {
                    // Check for specific staff member
                    return in_array($value, array_column($staffMentions, 'name'));
                }
                // Any staff mention
                return !empty($staffMentions);

            case 'area_mention':
                $areaMentions = $aiData['areas'] ?? [];
                if (is_array($areaMentions)) {
                    return in_array($value, array_column($areaMentions, 'name'));
                }
                return in_array($value, (array) $areaMentions);

            case 'emotion':
                $emotions = $aiData['emotions'] ?? [];
                $threshold = $condition['threshold'] ?? 0.5;
                return isset($emotions[$value]) && $emotions[$value]['score'] >= $threshold;

            case 'service_type':
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
        $value = strtolower($value);

        switch ($operator) {
            case 'equals':
                return $sentiment === $value;
            case 'not_equals':
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
        switch ($operator) {
            case 'equals':
                return abs($actual - $value) < 0.01; // Float comparison
            case 'not_equals':
                return abs($actual - $value) >= 0.01;
            case 'greater_than':
                return $actual > $value;
            case 'less_than':
                return $actual < $value;
            case 'between':
                return $actual >= $value[0] && $actual <= $value[1];
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
        $value = strtolower($value);

        switch ($operator) {
            case 'contains':
                return str_contains($text, $value);
            case 'equals':
                return $text === $value;
            case 'not_equals':
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
