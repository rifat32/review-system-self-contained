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
                            'name' => 'Review System Service monthly amount',
                        ],
                        'unit_amount' => $service_plan->price * 100, // Amount in cents
                        'recurring' => [
                            'interval' => "month",
                            'interval_count' => $service_plan->duration_months ?: 1,
                        ],
                    ],
                    'quantity' => 1,
                ]
            ],
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
                // Note: We'll copy this mail class later
                Mail::to(['kids20acc@gmail.com', 'ralashwad@gmail.com'])->send(new UserPaymentFailed($user));
            } catch (\Exception $e) {
                Log::error("Failed to send payment failed email: " . $e->getMessage());
            }
        }

        return redirect()->to(env("FRONT_END_URL") . "/verify/business?status=failed");
    }
}
