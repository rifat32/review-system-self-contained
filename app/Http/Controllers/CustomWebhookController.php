<?php

namespace App\Http\Controllers;

use App\Mail\UserPaymentFailed;
use App\Mail\UserPaymentSuccess;
use App\Mail\UserRegistered;
use App\Mail\UserSubscriptionRenewed;
use App\Models\ServicePlan;
use App\Models\BusinessSubscription;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController;

class CustomWebhookController extends WebhookController
{
    /**
     * Handle a Stripe webhook call.
     *
     * @param  Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();

        log_message([
            'level' => 'info',
            'message' => 'Webhook received payload: ' . $payload
        ], 'stripe.log');

        try {
            $payload = json_decode($payload, true);
            $eventType = $payload['type'] ?? null;

            log_message([
                'level' => 'info',
                'message' => 'Event Type: ' . $eventType
            ], 'stripe.log');

            if ($eventType === 'checkout.session.completed') {
                $this->handleChargeSucceeded($payload['data']['object']);
            }

            if ($eventType === 'invoice.payment_succeeded') {
                $this->handleSubscriptionPaymentSucceeded($payload['data']['object']);
            }

            if ($eventType === 'payment_intent.succeeded') {
                $this->handlePaymentIntentSucceeded($payload['data']['object']);
            }

            if ($eventType === 'payment_intent.payment_failed') {
                $this->handlePaymentIntentFailed($payload['data']['object']);
            }

            return response()->json(['message' => 'Webhook received']);
        } catch (Exception $e) {
            log_message([
                'level' => 'error',
                'message' => "Webhook processing error: " . $e->getMessage()
            ], 'stripe.log');
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function handleChargeSucceeded($data)
    {
        $amount = isset($data['amount_total']) ? $data['amount_total'] / 100 : null;
        $customerID = $data['customer'] ?? null;
        $metadata = $data["metadata"] ?? [];

        $user = User::where("stripe_id", $customerID)->first();
        if (!$user) {
            log_message([
                'level' => 'error',
                'message' => "User not found for customer ID: $customerID"
            ], 'stripe.log');
            return;
        }

        $service_plan = !empty($metadata["service_plan_id"])
            ? ServicePlan::find($metadata["service_plan_id"])
            : ServicePlan::find($user->business->service_plan_id);

        if (!$service_plan) {
            log_message([
                'level' => 'error',
                'message' => "Service plan not found for user ID: $user->id"
            ], 'stripe.log');
            return;
        }

        $subscription = BusinessSubscription::create([
            'business_id' => $user->business_id,
            'service_plan_id' => $service_plan->id,
            'start_date' => now(),
            'end_date' => now()->addMonths($service_plan->duration_months ?: 1),
            'amount' => $amount,
            'paid_at' => now(),
            'transaction_id' => $data['id'],
            'openai_token_limit' => $service_plan->openai_token_limit,
            'status' => 'active'
        ]);

        // Synchronize limit to business table
        $user->business->update([
            'openai_token_limit' => $service_plan->openai_token_limit,
            'service_plan_id' => $service_plan->id
        ]);

        if (env("SEND_EMAIL") == true) {
            try {
                Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com'])->send(new UserRegistered($user, $subscription));
            } catch (Exception $e) {
                log_message([
                    'level' => 'error',
                    'message' => "Failed to send registration email: " . $e->getMessage()
                ], 'stripe.log');
            }
        }
    }

    protected function handleSubscriptionPaymentSucceeded($invoice)
    {
        if (isset($invoice['subscription'])) {
            $lastIndex = count($invoice['lines']['data']) - 1;
            $amount = isset($invoice['lines']['data'][$lastIndex]['amount'])
                ? $invoice['lines']['data'][$lastIndex]['amount'] / 100
                : null;

            $customerID = $invoice['customer'] ?? null;
            $subscriptionID = $invoice['subscription'];
            $metadata = $invoice["subscription_details"]["metadata"] ?? [];

            $periodStart = isset($invoice['lines']['data'][0]['period']['start'])
                ? Carbon::createFromTimestamp($invoice['lines']['data'][0]['period']['start'])
                : null;

            $periodEnd = isset($invoice['lines']['data'][0]['period']['end'])
                ? Carbon::createFromTimestamp($invoice['lines']['data'][0]['period']['end'])
                : null;

            $user = User::where("stripe_id", $customerID)->first();

            if (!$user) {
                log_message([
                    'level' => 'error',
                    'message' => "User not found for customer ID: $customerID"
                ], 'stripe.log');
                return;
            }

            $service_plan = !empty($metadata["service_plan_id"])
                ? ServicePlan::find($metadata["service_plan_id"])
                : ServicePlan::find($user->business->service_plan_id);

            if (!$service_plan) {
                log_message([
                    'level' => 'error',
                    'message' => "Service plan not found for user ID: $user->id"
                ], 'stripe.log');
                return;
            }

            $subscription = BusinessSubscription::create([
                'business_id' => $user->business_id,
                'service_plan_id' => $service_plan->id,
                'start_date' => $periodStart ?: now(),
                'end_date' => $periodEnd ?: now()->addMonths($service_plan->duration_months ?: 1),
                'amount' => $amount,
                'paid_at' => now(),
                'transaction_id' => $invoice['id'],
                'stripe_id' => $subscriptionID,
                'openai_token_limit' => $service_plan->openai_token_limit,
                'status' => 'active'
            ]);

            // Synchronize limit to business table
            $user->business->update([
                'openai_token_limit' => $service_plan->openai_token_limit,
                'service_plan_id' => $service_plan->id
            ]);

            if (env("SEND_EMAIL") == true) {
                try {
                    // SEND SUBSCRIPTION RENEWED EMAIL TO BUSINESS OWNER AND ADMINS
                    $recipients = array_filter(array_unique([$user->email, 'kids20acc@gmail.com', 'ralashwad@gmail.com', 'rony.mia7800@gmail.com']));
                    Mail::to(users: $recipients)->send(mailable: new UserSubscriptionRenewed(user: $user, subscription: $subscription));
                } catch (Exception $e) {
                    log_message([
                        'level' => 'error',
                        'message' => "Failed to send renewal email: " . $e->getMessage()
                    ], 'stripe.log');
                }
            }
        }
    }

    private function decryptId(?string $id): ?string
    {
        if (empty($id)) return null;
        if (is_numeric($id)) return $id;

        if (strlen(string: $id) >= 20) {
            $stripped = substr(string: $id, offset: 10, length: -10);
            $decoded = base64_decode(strtr($stripped, '-_', '+/'));
            if ($decoded !== false) return $decoded;
        }

        $decoded = base64_decode(strtr($id, '-_', '+/'), true);
        if ($decoded !== false && is_numeric($decoded)) {
            return $decoded;
        }

        return $id;
    }

    protected function handlePaymentIntentSucceeded($data)
    {
        $amount = isset($data['amount_received']) ? $data['amount_received'] / 100 : null;
        $metadata = $data['metadata'] ?? [];

        $businessId = $this->decryptId($metadata['business_id'] ?? null);
        $planId = $this->decryptId($metadata['plan_id'] ?? null);

        if (!$businessId || !$planId) {
            log_message([
                'level' => 'info',
                'message' => "PaymentIntent succeeded, but metadata is missing business_id or plan_id. Ignoring."
            ], 'stripe.log');
            return;
        }

        // CHECK IF ALREADY PROVISIONED (e.g., via confirm-payment endpoint)
        $existingSub = \App\Models\BusinessSubscription::where('transaction_id', $data['id'])->first();
        if ($existingSub) {
            log_message([
                'level' => 'info',
                'message' => "Subscription already provisioned for PaymentIntent: " . $data['id']
            ], 'stripe.log');
            return;
        }

        $business = \App\Models\Business::find($businessId);
        if (!$business) {
            log_message([
                'level' => 'error',
                'message' => "Business not found for ID: $businessId"
            ], 'stripe.log');
            return;
        }

        $user = User::where('business_id', $businessId)->first();
        if (!$user) {
            log_message([
                'level' => 'error',
                'message' => "User not found for business ID: $businessId"
            ], 'stripe.log');
            return;
        }

        $service_plan = ServicePlan::find($planId);
        if (!$service_plan) {
            log_message([
                'level' => 'error',
                'message' => "Service plan not found for plan ID: $planId"
            ], 'stripe.log');
            return;
        }

        // 1. FIND THE EXISTING ACTIVE SUBSCRIPTION TO GET THE END DATE
        $latestSubscription = \App\Models\BusinessSubscription::where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('end_date', 'desc')
            ->first();

        // 2. DETERMINE THE NEW START DATE
        // If they have an active plan that hasn't expired yet, start the new one when it ends.
        // Otherwise, start it right now.
        if ($latestSubscription && $latestSubscription->end_date > now()) {
            $newStartDate = clone $latestSubscription->end_date;
        } else {
            $newStartDate = now();
        }

        // 3. CREATE THE NEW SUBSCRIPTION RECORD
        $durationEndDate = (clone $newStartDate)->addMonths($service_plan->duration_months ?: 1);
        $finalEndDate = !empty($metadata['end_date']) ? \Carbon\Carbon::parse($metadata['end_date']) : $durationEndDate;

        $subscription = \App\Models\BusinessSubscription::create([
            'business_id' => $businessId,
            'service_plan_id' => $service_plan->id,
            'start_date' => $newStartDate,
            'end_date' => $finalEndDate,
            'amount' => $amount,
            'paid_at' => now(),
            'transaction_id' => $data['id'],
            'openai_token_limit' => $service_plan->openai_token_limit,
            'status' => 'active'
        ]);

        // 4. SYNCHRONIZE LIMITS AND RESET DISCOUNTS IN THE BUSINESS TABLE
        $business->update([
            'openai_token_limit' => $service_plan->openai_token_limit,
            'service_plan_id' => $service_plan->id,
            'service_plan_discount_code' => null,
            'service_plan_discount_amount' => 0,
            'trial_end_date' => null,
            'start_date' => $newStartDate
        ]);

        if (env("SEND_EMAIL") == true) {
            try {
                // PREPARE PAYMENT SUCCESS DETAILS
                $payment_details = [
                    'amount' => $amount,
                    'currency' => strtoupper(string: $data['currency']),
                    'plan_name' => $service_plan->name ?? null,
                    'transaction_id' => $data['id'],
                    'dashboard_url' => env(key: 'FRONT_END_DASHBOARD_URL') . '/'
                ];

                // SEND PAYMENT SUCCESS EMAIL TO USER
                $recipients = array_filter(array_unique([$user->email, 'ralashwad@gmail.com']));
                Mail::to(users: $recipients)->send(mailable: new UserPaymentSuccess(user: $user, paymentDetails: $payment_details));
                Mail::to(users: ['kids20acc@gmail.com', 'ralashwad@gmail.com', 'rony.mia7800@gmail.com'])->send(mailable: new UserRegistered(user: $user, subscription: $subscription));
            } catch (Exception $e) {
                log_message([
                    'level' => 'error',
                    'message' => "Failed to send registration/payment email for PaymentIntent: " . $e->getMessage()
                ], 'stripe.log');
            }
        }
    }

    protected function handlePaymentIntentFailed($data)
    {
        $metadata = $data['metadata'] ?? [];
        $businessId = $this->decryptId($metadata['business_id'] ?? null);

        if (!$businessId) {
            log_message([
                'level' => 'info',
                'message' => "PaymentIntent failed, but metadata is missing business_id. Ignoring."
            ], 'stripe.log');
            return;
        }

        // FIND USER BY BUSINESS ID
        $user = User::where('business_id', $businessId)->first();
        if (!$user) {
            log_message([
                'level' => 'error',
                'message' => "User not found for business ID: $businessId during payment failure."
            ], 'stripe.log');
            return;
        }

        // EXTRACT PAYMENT FAILURE DETAILS FROM STRIPE WEBHOOK EVENT
        $amount_cents = $data['amount'] ?? null;
        $amount = $amount_cents ? ($amount_cents / 100) : null;
        $currency = strtoupper($data['currency']);
        $failure_reason = $data['last_payment_error']['message'] ?? 'The payment attempt was declined by your card issuer or bank.';
        $plan_name = $metadata['plan_name'] ?? null;

        $payment_details = [
            'amount' => $amount,
            'currency' => $currency,
            'failure_reason' => $failure_reason,
            'plan_name' => $plan_name,
            'retry_url' => env('FRONT_END_DASHBOARD_URL', env('FRONT_END_URL', 'http://localhost:3000')) . '/billing'
        ];

        if (env("SEND_EMAIL") == true) {
            try {
                // SEND NOTIFICATION EMAIL TO USER AND ADMIN
                $recipients = array_filter(array_unique([$user->email, 'ralashwad@gmail.com', 'kids20acc@gmail.com', 'rony.mia7800@gmail.com']));
                Mail::to(users: $recipients)->send(mailable: new UserPaymentFailed(user: $user, paymentDetails: $payment_details));
            } catch (Exception $e) {
                log_message([
                    'level' => 'error',
                    'message' => "Failed to send payment failed email: " . $e->getMessage()
                ], 'stripe.log');
            }
        }

        log_message([
            'level' => 'info',
            'message' => "PaymentIntent failed for User: {$user->id} (Business: {$businessId})."
        ], 'stripe.log');
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
    public function createIntent(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'plan_id'     => 'required|string|exists:service_plans,id',
            'business_id' => 'required|string|exists:businesses,id',
            'reseller_id' => 'nullable|string',
            'amount'      => 'required|integer',
            'currency'    => 'nullable|string',
            'end_date'    => 'nullable|date'
        ]);

        $decryptedPlanId = $this->decryptId($request->input('plan_id'));
        $decryptedBusinessId = $this->decryptId($request->input('business_id'));
        $decryptedResellerId = $request->has('reseller_id') ? $this->decryptId($request->input('reseller_id')) : null;

        $plan = \App\Models\ServicePlan::find($decryptedPlanId);

        if ($plan) {
            $amountInCents = (int) (($plan->price + $plan->set_up_amount) * 100);
        } else {
            $amountInCents = $request->input('amount');
        }

        $currency = 'GBP';
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        try {
            $webhookUrl = rtrim(env('APP_URL', 'https://api-backend.feedgenius.ai'), '/') . '/api/webhooks/stripe';
            if (!\Illuminate\Support\Facades\Cache::has('stripe_webhook_registered_v2')) {
                $endpoints = $stripe->webhookEndpoints->all(['limit' => 100]);
                $exists = false;
                foreach ($endpoints->data as $endpoint) {
                    if ($endpoint->url === $webhookUrl) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $stripe->webhookEndpoints->create([
                        'url' => $webhookUrl,
                        'enabled_events' => [
                            'payment_intent.succeeded',
                            'payment_intent.payment_failed'
                        ],
                    ]);
                }
                \Illuminate\Support\Facades\Cache::put('stripe_webhook_registered_v2', true, now()->addDays(30));
            }
        } catch (\Exception $e) {
            log_message(['level' => 'error', 'message' => 'Failed to auto-register webhook: ' . $e->getMessage()], 'stripe.log');
        }

        try {
            $metadata = [
                'plan_id'     => $decryptedPlanId,
                'business_id' => $decryptedBusinessId,
                'reseller_id' => $decryptedResellerId,
            ];

            if ($request->has('end_date')) {
                $metadata['end_date'] = \Carbon\Carbon::parse($request->input('end_date'))->toDateTimeString();
            }

            $paymentIntent = $stripe->paymentIntents->create([
                'amount'   => $amountInCents,
                'currency' => $currency,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return response()->json([
                'success'       => true,
                'message'       => 'Payment Intent created successfully',
                'data'          => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create PaymentIntent: ' . $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }
}
