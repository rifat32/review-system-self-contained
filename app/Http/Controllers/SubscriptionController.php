<?php

namespace App\Http\Controllers;

use App\Mail\UserPaymentFailed;
use App\Models\Business;
use App\Models\ServicePlan;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\WebhookEndpoint;

class SubscriptionController extends Controller
{
    public function redirectUserToStripe(Request $request)
    {
        $id = $request->id;

        // Check if the string is at least 20 characters long to ensure it has enough characters to remove
        if (strlen($id) >= 20) {
            // Remove the first ten characters and the last ten characters
            $trimmed_id = substr($id, 10, -10);
        } else {
            throw new Exception("invalid id");
        }

        $business = Business::findOrFail($trimmed_id);
        $user = User::findOrFail($business->OwnerID);
        Auth::login($user);

        Stripe::setApiKey(config('cashier.stripe.secret'));

        $service_plan = ServicePlan::where([
            "id" => $business->service_plan_id
        ])->first();

        if (!$service_plan) {
            return response()->json([
                "message" => "no service plan found"
            ], 404);
        }

        if (empty($user->stripe_id)) {
            $stripe_customer = \Stripe\Customer::create([
                'email' => $user->email,
            ]);

            $user->stripe_id = $stripe_customer->id;
            $user->save();
        }

        $line_items = [];

        // Add set up amount if greater than 0
        if ($service_plan->set_up_amount > 0) {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'GBP',
                    'product_data' => [
                        'name' => 'Service set up amount',
                    ],
                    'unit_amount' => $service_plan->set_up_amount * 100, // Amount in cents
                ],
                'quantity' => 1,
            ];
        }

        // Add monthly amount
        $line_items[] = [
            'price_data' => [
                'currency' => 'GBP',
                'product_data' => [
                    'name' => 'Service monthly amount',
                ],
                'unit_amount' => $service_plan->price * 100, // Amount in cents
                'recurring' => [
                    'interval' => "month",
                    'interval_count' => $service_plan->duration_months ?: 1,
                ],
            ],
            'quantity' => 1,
        ];

        $session_data = [
            'payment_method_types' => ['card'],
            'metadata' => [
                'our_url' => route('stripe.webhook'),
                'service_plan_id' => $service_plan->id,
                'service_plan_name' => $service_plan->name,
            ],
            'line_items' => $line_items,
            'subscription_data' => [
                'metadata' => [
                    'our_url' => route('stripe.webhook'),
                    'service_plan_id' => $service_plan->id,
                    'service_plan_name' => $service_plan->name,
                ],
            ],
            'customer' => $user->stripe_id,
            'mode' => 'subscription',
            'success_url' => route('subscription.success_payment', ['user_id' => base64_encode($user->id)]),
            'cancel_url' => route('subscription.failed_payment', ['user_id' => base64_encode($user->id)]),
        ];

        // Apply discount if set_up_amount + price > 0 (to avoid issues with free sessions needing credit cards anyway)
        if (!empty($business->service_plan_discount_amount) && $business->service_plan_discount_amount > 0) {
            $coupon = \Stripe\Coupon::create([
                'amount_off' => $business->service_plan_discount_amount * 100, // Amount in cents
                'currency' => 'GBP',
                'duration' => 'once',
                'name' => $business->service_plan_discount_code,
            ]);

            $session_data["discounts"] = [
                [
                    'coupon' => $coupon->id,
                ],
            ];
        }

        $session = Session::create($session_data);

        return redirect()->to($session->url);
    }

    public function redirectUserToStripeRenewal(Request $request)
    {
        $id = $request->id;

        if (strlen($id) >= 20) {
            $trimmed_id = substr($id, 10, -10);
        } else {
            throw new Exception("invalid id");
        }

        $business = Business::findOrFail($trimmed_id);
        $user = User::findOrFail($business->OwnerID);
        Auth::login($user);

        Stripe::setApiKey(config('cashier.stripe.secret'));

        $service_plan = ServicePlan::where("id", $business->service_plan_id)->first();

        if (!$service_plan) {
            return response()->json([
                "message" => "no service plan found"
            ], 404);
        }

        if (empty($user->stripe_id)) {
            return response()->json([
                "message" => "Stripe customer not found. User must subscribe first."
            ], 404);
        }

        // Retrieve the subscription for the user
        $subscriptions = \Stripe\Subscription::all([
            'customer' => $user->stripe_id,
            'status' => 'active'
        ]);

        if (empty($subscriptions->data)) {
            return response()->json([
                "message" => "No active subscriptions found for renewal."
            ], 404);
        }

        $current_subscription = $subscriptions->data[0];

        // Check if the subscription needs to be renewed
        if ($current_subscription->current_period_end > time()) {
            return response()->json([
                "message" => "Subscription is still active. Renewal is not required."
            ], 400);
        }

        // Create a new Stripe checkout session for renewal
        $session_data = [
            'payment_method_types' => ['card'],
            'metadata' => [
                'our_url' => route('stripe.webhook'),
                'service_plan_id' => $service_plan->id,
                'service_plan_name' => $service_plan->name,
            ],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'GBP',
                        'product_data' => [
                            'name' => 'Service monthly renewal',
                        ],
                        'unit_amount' => $service_plan->price * 100, // Amount in cents
                        'recurring' => [
                            'interval' => 'month',
                            'interval_count' => $service_plan->duration_months ?: 1,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'customer' => $user->stripe_id,
            'mode' => 'subscription',
            'success_url' => route('subscription.success_renewal', ['user_id' => base64_encode($user->id)]),
            'cancel_url' => route('subscription.failed_renewal', ['user_id' => base64_encode($user->id)]),
        ];

        $session = Session::create($session_data);

        return redirect()->to($session->url);
    }

    public function stripePaymentSuccess(Request $request)
    {
        return redirect()->to(env("FRONT_END_URL") . "/verify/business?status=success");
    }

    public function stripePaymentFailed(Request $request)
    {
        $user_id = base64_decode($request->query('user_id'));
        $user = User::find($user_id);

        if ($user && env("SEND_EMAIL") == true) {
            try {
                Mail::to(['ralashwad@gmail.com'])->send(new UserPaymentFailed($user));
            } catch (\Exception $e) {
                Log::error("Failed to send payment failed email: " . $e->getMessage());
            }
        }

        return redirect()->to(env("FRONT_END_URL") . "/verify/business?status=failed");
    }

    public function stripeRenewPaymentSuccess(Request $request)
    {
        return redirect()->to(env("FRONT_END_URL") . "/verify/business?status=success");
    }

    public function stripeRenewPaymentFailed(Request $request)
    {
        return redirect()->to(env("FRONT_END_URL") . "/verify/business?status=failed");
    }
}
