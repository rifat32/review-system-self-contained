<?php

namespace App\Utils;

use App\Models\Business;
use App\Models\OpenAITokenUsage;
use Carbon\Carbon;

class TokenUsageUtil
{
    /**
     * Calculate AI token usage for a business based on their current billing cycle.
     *
     * @param Business $business
     * @param mixed $currentSubscription Optional pre-loaded current subscription
     * @param bool|null $isOnTrial Optional pre-calculated trial status
     * @param string|null $trialEndDate Optional pre-loaded trial end date
     * @return array
     */
    public static function calculateUsage(
        Business $business, 
        $currentSubscription = null, 
        $isOnTrial = null, 
        $trialEndDate = null
    ): array {
        $usage = [
            'ai_tokens' => [
                'used' => 0,
                'limit' => 0,
                'percentage' => 0,
            ],
            'reviews' => [
                'used' => 0,
                'limit' => 0,
                'remaining' => 0,
                'percentage' => 0,
            ],
            'billing_cycle_start' => null,
            'billing_cycle_end' => null,
        ];

        if (!$business->service_plan && $business->openai_token_limit === null) {
            return $usage;
        }

        // Use the business's overridden limit (or fallback limit), rather than the plan's default
        $usage['ai_tokens']['limit'] = $business->openai_token_limit;
        $usage['reviews']['limit'] = $business->estimated_reviews_limit;

        // Resolve dependencies if not provided
        $currentSubscription = $currentSubscription ?? $business->current_subscription;
        $trialEndDate = $trialEndDate ?? $business->trial_end_date;
        
        if ($isOnTrial === null && !empty($trialEndDate) && $trialEndDate !== '0000-00-00') {
            $parsed = Carbon::parse($trialEndDate);
            $isOnTrial = !$parsed->isPast() || $parsed->isToday();
        }

        $startDate = null;
        $endDate = null;

        if ($currentSubscription) {
            $startDate = $currentSubscription->start_date;
            $endDate = $currentSubscription->end_date;
        } else if ($isOnTrial) {
            $startDate = $business->start_date;
            $endDate = $trialEndDate;
        }

        if ($startDate) {
            $usage['billing_cycle_start'] = $startDate;
            $usage['billing_cycle_end'] = $endDate;

            $query = OpenAITokenUsage::where('business_id', $business->id)
                ->where('created_at', '>=', $startDate);

            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }

            $usage['ai_tokens']['used'] = (int) $query->sum('total_tokens');
            $usage['reviews']['used'] = (int) $query->count();

            if ($usage['ai_tokens']['limit'] > 0) {
                $percentage = ($usage['ai_tokens']['used'] / $usage['ai_tokens']['limit']) * 100;
                $usage['ai_tokens']['percentage'] = (float) number_format($percentage, 1, '.', '');
            }

            if ($usage['reviews']['limit'] === -1) {
                $usage['reviews']['remaining'] = -1; // Unlimited
            } else {
                $usage['reviews']['remaining'] = max(0, $usage['reviews']['limit'] - $usage['reviews']['used']);
                if ($usage['reviews']['limit'] > 0) {
                    $percentage = ($usage['reviews']['used'] / $usage['reviews']['limit']) * 100;
                    $usage['reviews']['percentage'] = (float) number_format($percentage, 1, '.', '');
                }
            }
        }

        return $usage;
    }
}
