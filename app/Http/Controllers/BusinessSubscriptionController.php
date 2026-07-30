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

        $usage = \App\Utils\TokenUsageUtil::calculateUsage(
            business: $business,
            currentSubscription: $currentSubscription,
            isOnTrial: $isOnTrial,
            trialEndDate: $trialEndDate
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription retrieved successfully',
            'data'    => [
                'subscription_status'  => $status,
                'is_on_trial'          => $isOnTrial,
                'trial_end_date'       => $trialEndDate,
                'trial_days_remaining' => $trialDaysLeft,
                'usage'                => $usage,
                'current_plan'         => $business->service_plan,
                'current_subscription' => $currentSubscription,
                'subscription_history' => $business->subscriptions()
                    ->with('service_plan:id,name,price,duration_months')
                    ->orderByDesc('id')
                    ->get(),
            ],
        ], Response::HTTP_OK);
    }
    /**
     * @OA\Post(
     *   path="/v1.0/subscriptions/create-intent",
     *   operationId="createSubscriptionIntent",
     *   tags={"subscription_management"},
     *   security={{"bearerAuth":{}}},
     *   summary="Create a Stripe PaymentIntent for subscription checkout",
     *   description="Creates a PaymentIntent based on the selected plan. Expects encrypted plan_id and business_id.",
     *   @OA\RequestBody(
     *       required=true,
     *       @OA\JsonContent(
     *           @OA\Property(property="plan_id", type="string", description="Encrypted Plan ID"),
     *           @OA\Property(property="business_id", type="string", description="Encrypted Business ID"),
     *           @OA\Property(property="reseller_id", type="string", description="Encrypted Reseller ID (optional)"),
     *           @OA\Property(property="amount", type="integer", description="Fallback amount in cents"),
     *           @OA\Property(property="currency", type="string", default="gbp")
     *       )
     *   ),
     *   @OA\Response(response=200, description="Successful operation", @OA\JsonContent())
     * )
     */
    public function createIntent(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate(rules: [
            'plan_id'     => 'required|string',
            'business_id' => 'required|string',
            'reseller_id' => 'nullable|string',
            'amount'      => 'required|integer',
            'currency'    => 'nullable|string'
        ]);

        $decryptedPlanId = $this->decryptId(id: $request->input(key: 'plan_id'));
        $decryptedBusinessId = $this->decryptId(id: $request->input(key: 'business_id'));
        $decryptedResellerId = $request->has(key: 'reseller_id') ? $this->decryptId(id: $request->input(key: 'reseller_id')) : null;

        // GET PLAN
        $plan = \App\Models\ServicePlan::find(id: $decryptedPlanId);
        
        // CALCULATE AMOUNT SECURELY ON THE BACKEND
        if ($plan) {
            $amountInCents = (int) (($plan->price + $plan->set_up_amount) * 100);
        } else {
            $amountInCents = $request->input(key: 'amount');
        }

        $currency = $request->input(key: 'currency', default: 'gbp');

        // CREATE PAYMENT INTENT
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        try {
            $paymentIntent = $stripe->paymentIntents->create(params: [
                'amount'   => $amountInCents,
                'currency' => $currency,
                'metadata' => [
                    'plan_id'     => $decryptedPlanId,
                    'business_id' => $decryptedBusinessId,
                    'reseller_id' => $decryptedResellerId,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return response()->json(data: [
                'success'       => true,
                'message'       => 'Payment Intent created successfully',
                'client_secret' => $paymentIntent->client_secret,
                'data'          => [
                    'client_secret' => $paymentIntent->client_secret
                ]
            ], status: Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(data: [
                'success' => false,
                'message' => 'Failed to create PaymentIntent: ' . $e->getMessage(),
                'data'    => []
            ], status: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Decrypts the frontend-provided ID by removing the first and last 10 characters.
     */
    private function decryptId(?string $id): ?string
    {
        if (empty($id)) {
            return null;
        }
        if (strlen(string: $id) >= 20) {
            return substr(string: $id, offset: 10, length: -10);
        }
        return $id;
    }
}
