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

        if ($trialEndDate) {
            $parsed    = Carbon::parse($trialEndDate);
            $isOnTrial = !$parsed->isPast() || $parsed->isToday();
            $trialDaysLeft = max(0, (int) now()->diffInDays($parsed, false));
        }

        $currentSubscription = $business->current_subscription;

        if ($isOnTrial && (!$currentSubscription || $currentSubscription->stripe_status === 'trialing')) {
            $status = 'trial';
        } elseif ($currentSubscription && $currentSubscription->stripe_status !== 'trialing') {
            $status = 'active';
        } elseif ($business->is_subscribed) {
            $status = 'active';
        } else {
            $status = $trialEndDate ? 'expired' : 'none';
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription retrieved successfully',
            'data'    => [
                'subscription_status'  => $status,
                'is_on_trial'          => $isOnTrial,
                'trial_end_date'       => $trialEndDate,
                'trial_days_remaining' => $trialDaysLeft,
                'current_plan'         => $business->service_plan,
                'current_subscription' => $currentSubscription,
                'subscription_history' => $business->subscriptions()
                    ->with('service_plan:id,name,price,duration_months')
                    ->orderByDesc('id')
                    ->get(),
            ],
        ], Response::HTTP_OK);
    }
}
