<?php

namespace App\Services\Rule;

use App\Models\ReviewNew;
use Illuminate\Support\Facades\Log;

class ConditionBuilderService
{
    public static function validateConditionTree(array $conditions): array
    {
        $errors = [];
        if (isset($conditions['logic']) && !in_array($conditions['logic'], ['AND', 'OR'])) $errors[] = "Invalid logic operator: {$conditions['logic']}";
        $items = $conditions['conditions'] ?? $conditions;
        if (!is_array($items) || empty($items)) { $errors[] = "Condition tree must contain at least one condition"; return $errors; }
        foreach ($items as $index => $condition) {
            if (isset($condition['group'])) { $errors = array_merge($errors, self::validateConditionTree($condition['group'])); continue; }
            $validSources = ['Comment', 'Rating', 'Staff', 'Area', 'Emotion', 'Trend'];
            $validTypes = ['sentiment', 'rating', 'keyword', 'staff_mention', 'area_mention', 'emotion', 'intensity', 'frequency', 'trend_direction'];
            $validOperators = ['equals', 'eq', 'contains', 'greater_than', 'gt', 'less_than', 'lt', 'greater_than_or_equal', 'gte', 'less_than_or_equal', 'lte', 'between', 'regex', 'not_equals', 'neq', 'starts_with', 'ends_with', 'exists'];
            if (!isset($condition['source']) || !in_array($condition['source'], $validSources)) $errors[] = "Condition at index {$index} has invalid source";
            if (!isset($condition['type']) || !in_array($condition['type'], $validTypes)) $errors[] = "Condition at index {$index} has invalid type";
            if (!isset($condition['operator']) || !in_array($condition['operator'], $validOperators)) $errors[] = "Condition at index {$index} has invalid operator";
            if (($condition['operator'] ?? null) === 'between') {
                if (!isset($condition['value']) || !is_array($condition['value']) || count($condition['value']) < 2) $errors[] = "Condition at index {$index} using between must have two values";
            } elseif (($condition['operator'] ?? null) !== 'exists' && !array_key_exists('value', $condition)) { $errors[] = "Condition at index {$index} is missing value"; }
        }
        return $errors;
    }

    public static function evaluateConditions($conditionData, ReviewNew $review, array &$aiData, string $defaultLogic = 'AND'): bool
    {
        while (is_string($conditionData)) {
            $decoded = json_decode($conditionData, true);
            if (json_last_error() !== JSON_ERROR_NONE || $decoded === $conditionData) break;
            $conditionData = $decoded;
        }
        if (!is_array($conditionData)) {
            Log::warning("Condition data is not an array", ['review_id' => $review->id]);
            return false;
        }
        $logic = $conditionData['logic'] ?? $defaultLogic;
        $conditions = $conditionData['conditions'] ?? [];
        if (empty($conditions)) {
            Log::warning("Condition tree is empty during execution", ['review_id' => $review->id]);
            return false;
        }

        $results = []; $localMatches = [];
        foreach ($conditions as $condition) {
            if (isset($condition['group']) || (isset($condition['logic']) && isset($condition['conditions']))) {
                $nestedAiData = $aiData;
                $res = self::evaluateConditions($condition['group'] ?? $condition, $review, $nestedAiData, $defaultLogic);
                if ($res) $localMatches = array_merge($localMatches, $nestedAiData['matched_conditions'] ?? []);
            } else {
                $res = self::evaluateSingleCondition($condition, $review, $aiData);
                if ($res) {
                    $localMatches[] = $condition;
                    Log::debug("Single condition matched", ['review_id' => $review->id, 'condition' => $condition]);
                } else {
                    Log::debug("Single condition failed", ['review_id' => $review->id, 'condition' => $condition]);
                }
            }
            $results[] = $res;
        }
        $final = strtoupper($logic) === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
        if ($final) {
            $aiData['matched_conditions'] = array_merge($aiData['matched_conditions'] ?? [], $localMatches);
            Log::info("Condition group evaluation result", ['review_id' => $review->id, 'logic' => $logic, 'result' => $final, 'match_count' => count($localMatches)]);
        }
        return $final;
    }

    private static function evaluateSingleCondition(array $condition, ReviewNew $review, array $aiData): bool
    {
        $source = $condition['source'] ?? null; $type = $condition['type'] ?? ''; $operator = $condition['operator'] ?? 'equals'; $value = $condition['value'] ?? null;

        if ($operator === 'exists') {
            $hasData = match ($source) {
                'Staff' => (!empty($aiData['staff_intelligence']) || !empty($aiData['staff_mentions'])),
                'Area' => (!empty($aiData['area_insights']) || !empty($aiData['areas'])),
                'Sentiment', 'Emotion' => (isset($aiData['sentiment']) || isset($aiData['emotion'])),
                'Rating' => isset($review->calculated_rating),
                default => match ($type) {
                    'staff_mention' => (!empty($aiData['staff_intelligence']) || !empty($aiData['staff_mentions'])),
                    'area_mention' => (!empty($aiData['area_insights']) || !empty($aiData['areas'])),
                    'sentiment' => isset($aiData['sentiment']),
                    default => false
                }
            };
            if (!array_key_exists('value', $condition)) {
                return $hasData;
            }

            return (bool) $value === $hasData;
        }

        if ($source === 'Rating' || $type === 'rating') return self::matchNumeric($review->calculated_rating ?? 0, $operator, $value);

        if ($source === 'Comment' || $source === 'Emotion' || in_array($type, ['sentiment', 'keyword', 'emotion', 'intensity'])) {
            if ($type === 'sentiment') return self::matchSentiment($aiData['sentiment']['label'] ?? $aiData['overall_sentiment'] ?? 'neutral', $operator, $value);
            if ($type === 'keyword') return self::matchText($review->comment ?? $review->raw_text ?? '', $operator, $value);
            if ($type === 'emotion' || $type === 'intensity') {
                $raw = $aiData['emotion']['intensity'] ?? 'low';
                $intensityVal = is_numeric($raw) ? (float) $raw : (['low' => 0.3, 'medium' => 0.6, 'high' => 0.9][strtolower((string)$raw)] ?? 0.5);
                if ($type === 'intensity' || is_numeric($value)) return self::matchNumeric($intensityVal, $operator, $value);
                return self::matchSentiment($aiData['emotion']['primary'] ?? 'neutral', $operator, $value);
            }
        }

        if ($source === 'Trend') {
            $trend = $aiData['trend_data'] ?? [];
            if ($type === 'frequency') return self::matchNumeric($trend['frequency'] ?? 0, $operator, $value);
            if ($type === 'trend_direction') return self::matchText($trend['direction'] ?? '', $operator, $value);
        }

        if ($source === 'Staff' || $type === 'staff_mention') {
            $mentions = $aiData['staff_mentions'] ?? [];
            if ($value === true || $value === false) return (bool)$value === !empty($mentions);
            return in_array(strtolower($value), array_map(fn($s) => strtolower($s['name'] ?? ''), (array)$mentions));
        }

        if ($source === 'Area' || $type === 'area_mention') {
            $areas = $aiData['area_insights'] ?? $aiData['areas'] ?? [];
            if ($value === true || $value === false) return (bool)$value === !empty($areas);
            return in_array(strtolower($value), array_map(fn($a) => strtolower($a['area_name'] ?? $a['name'] ?? ''), (array)$areas));
        }
        return false;
    }

    private static function matchNumeric($actual, $op, $val)
    {
        $actual = (float)$actual; $val = is_array($val) ? array_map('floatval', $val) : (float)$val;
        return match ($op) {
            'equals', 'eq' => abs($actual - (is_array($val) ? $val[0] : $val)) < 0.01,
            'greater_than', 'gt' => $actual > (is_array($val) ? $val[0] : $val),
            'less_than', 'lt' => $actual < (is_array($val) ? $val[0] : $val),
            'greater_than_or_equal', 'gte' => $actual >= (is_array($val) ? $val[0] : $val),
            'less_than_or_equal', 'lte' => $actual <= (is_array($val) ? $val[0] : $val),
            'between' => is_array($val) && count($val) >= 2 && $actual >= $val[0] && $actual <= $val[1],
            default => false
        };
    }

    private static function matchSentiment($actual, $op, $val)
    {
        $actual = strtolower($actual ?? 'neutral'); $val = strtolower((string)$val);
        return match ($op) { 'equals', 'eq' => $actual === $val, 'not_equals', 'neq' => $actual !== $val, default => false };
    }

    private static function matchText($text, $op, $val)
    {
        $text = strtolower($text ?? ''); $val = strtolower((string)$val);
        return match ($op) {
            'contains' => str_contains($text, $val), 'equals', 'eq' => $text === $val, 'not_equals', 'neq' => $text !== $val,
            'starts_with' => str_starts_with($text, $val), 'ends_with' => str_ends_with($text, $val),
            'regex' => @preg_match($val, $text) === 1, default => false
        };
    }
}
