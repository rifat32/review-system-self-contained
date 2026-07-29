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
            'tokens_used' => 0,
            'tokens_limit' => 0,
            'usage_percentage' => 0,
            'billing_cycle_start' => null,
            'billing_cycle_end' => null,
        ];

        if (!$business->service_plan) {
            return $usage;
        }

        $usage['tokens_limit'] = $business->service_plan->openai_token_limit;

        // Resolve dependencies if not provided
        $currentSubscription = $currentSubscription ?? $business->current_subscription;
        $trialEndDate = $trialEndDate ?? $business->trial_end_date;
        
        if ($isOnTrial === null && $trialEndDate) {
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

            $usage['tokens_used'] = (int) $query->sum('total_tokens');

            if ($usage['tokens_limit'] > 0) {
                $usage['usage_percentage'] = round(($usage['tokens_used'] / $usage['tokens_limit']) * 100, 1);
            }
        }

        return $usage;
    }
}
