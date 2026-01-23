<?php

namespace App\Http\Controllers;

use App\Mail\UserRegistered;
use App\Mail\UserSubscriptionRenewed;
use App\Models\ServicePlan;
use App\Models\BusinessSubscription;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

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
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error("Invalid payload: " . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            Log::error("Invalid signature: " . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        try {
            $payload = json_decode($payload, true);
            $eventType = $payload['type'] ?? null;

            Log::info('Event Type: ' . $eventType);

            if ($eventType === 'checkout.session.completed') {
                $this->handleChargeSucceeded($payload['data']['object']);
            }

            if ($eventType === 'invoice.payment_succeeded') {
                $this->handleSubscriptionPaymentSucceeded($payload['data']['object']);
            }

            return response()->json(['message' => 'Webhook received']);
        } catch (Exception $e) {
            Log::error("Webhook processing error: " . $e->getMessage());
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
            Log::error("User not found for customer ID: $customerID");
            return;
        }

        $service_plan = !empty($metadata["service_plan_id"])
            ? ServicePlan::find($metadata["service_plan_id"])
            : ServicePlan::find($user->business->service_plan_id);

        if (!$service_plan) {
            Log::error("Service plan not found for user ID: $user->id");
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
                Log::error("Failed to send registration email: " . $e->getMessage());
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
                Log::error("User not found for customer ID: $customerID");
                return;
            }

            $service_plan = !empty($metadata["service_plan_id"])
                ? ServicePlan::find($metadata["service_plan_id"])
                : ServicePlan::find($user->business->service_plan_id);

            if (!$service_plan) {
                Log::error("Service plan not found for user ID: $user->id");
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
                    Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com'])->send(new UserSubscriptionRenewed($user, $subscription));
                } catch (Exception $e) {
                    Log::error("Failed to send renewal email: " . $e->getMessage());
                }
            }
        }
    }
}
