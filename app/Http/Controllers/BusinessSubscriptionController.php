<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BusinessSubscriptionController extends Controller
{
    /**
     * @OA\Get(
     *   path="/v1.0/my-subscription",
     *   operationId="getMySubscription",
     *   tags={"subscription_management"},
     *   security={{"bearerAuth":{}}},
     *   summary="Get authenticated business owner's subscription details",
     *   description="Returns the current plan, trial status, and subscription history.",
     *   @OA\Response(response=200, description="Successful operation", @OA\JsonContent())
     * )
     */
    public function getMySubscription(): JsonResponse
    {
        // GET AUTHENTICATED USER
        $user = Auth::user();

        $business = Business::with([
            'service_plan.modules',
            'current_subscription.service_plan',
            'subscriptions.service_plan',
        ])->findOrFail($user->business_id);

        $trialEndDate  = $business->trial_end_date;
        $isOnTrial     = false;
        $trialDaysLeft = 0;

        if (!empty($trialEndDate) && $trialEndDate !== '0000-00-00') {
            $parsed    = \Carbon\Carbon::parse($trialEndDate);
            $isOnTrial = !$parsed->isPast() || $parsed->isToday();
            $trialDaysLeft = max(0, (int) now()->startOfDay()->diffInDays($parsed->startOfDay(), false));
        }

        $latestSubscription = $business->subscriptions()
            ->where('status', 'active')
            ->orderByDesc('end_date')
            ->first();

        $subscriptionEndDate = $latestSubscription ? $latestSubscription->end_date : null;
        $subscriptionDaysRemaining = 0;

        if ($subscriptionEndDate) {
            $parsedEnd = \Carbon\Carbon::parse($subscriptionEndDate);
            $subscriptionDaysRemaining = max(0, (int) now()->diffInDays($parsedEnd, false));
        }

        $upcomingSubscriptions = $business->subscriptions()
            ->with('service_plan:id,name,price,duration_months')
            ->where('status', 'active')
            ->where('start_date', '>', now())
            ->orderBy('start_date', 'asc')
            ->get();

        $currentSubscription = $business->current_subscription;

        if ($isOnTrial && (!$currentSubscription || $currentSubscription->stripe_status === 'trialing')) {
            $status = 'trial';
        } elseif ($latestSubscription && $subscriptionDaysRemaining > 0) {
            $status = 'active';
        } else {
            $status = $subscriptionEndDate ? 'expired' : 'none';
        }

        $usage = \App\Utils\TokenUsageUtil::calculateUsage(
            business: $business,
            currentSubscription: $currentSubscription,
            isOnTrial: $isOnTrial,
            trialEndDate: $trialEndDate
        );

        $excludeIds = $upcomingSubscriptions->pluck('id')->toArray();
        if ($currentSubscription) {
            $excludeIds[] = $currentSubscription->id;
        }

        $responseData = [
            'subscription_status'           => $status,
            'is_on_trial'                   => $isOnTrial,
            'trial_end_date'                => $trialEndDate,
            'trial_days_remaining'          => $trialDaysLeft,
            'subscription_end_date'         => $subscriptionEndDate,
            'subscription_days_remaining'   => $subscriptionDaysRemaining,
            'usage'                         => $usage,
            'current_plan'                  => $business->service_plan,
            'current_subscription'          => $currentSubscription,
            'upcoming_subscriptions'        => $upcomingSubscriptions,
            'subscription_history'          => $business->subscriptions()
                ->with('service_plan:id,name,price,duration_months')
                ->whereNotIn('id', $excludeIds)
                ->orderByDesc('start_date')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Subscription retrieved successfully',
            'data'    => $responseData,
        ], Response::HTTP_OK);
    }
}
