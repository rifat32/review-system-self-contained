<?php

namespace App\Services\Rule;

use App\Models\AiRule;
use Illuminate\Support\Facades\Log;

class DefaultRuleSeederService
{
    /**
     * List of required default rules
     */
    private static array $requiredRules = [
        'SENTIMENT_ANALYSIS',
        'EMOTION_INTENSITY',
        'RATING_COMMENT_MISMATCH',
        'CATEGORY_ISSUE_DETECTION',
        'SERVICE_TYPE_DETECTION',
        'BUSINESS_AREA_DETECTION',
        'STAFF_MENTION_DETECTION',
        'STAFF_PERFORMANCE_RISK',
        'FLAG_AND_ALERT'
    ];





    /**
     * Get the list of required default rules
     */
    public static function getRequiredRuleKeys(): array
    {
        return self::$requiredRules;
    }
}
