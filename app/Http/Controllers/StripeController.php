<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Business;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeController extends Controller
{


    public function redirectUserToStripe(Request $request)
    {
        $id = $request->id;

        // Check if the string is at least 20 characters long to ensure it has enough characters to remove
        if (strlen($id) >= 20) {
            // Remove the first ten characters and the last ten characters
            $trimmed_id = substr($id, 10, -10);
            // $trimmedId now contains the string with the first ten and last ten characters removed
        } else {
            throw new Exception("invalid id");
        }
        $order = Order::findOrFail($trimmed_id);
        $user = User::findOrFail($order->customer_id);
        $business = Business::findOrFail($order->business_id);




        Stripe::setApiKey(config('cashier.stripe.secret'));



        $existing_customer = \Stripe\Customer::all(["email" => $user->email], ["limit" => 1])->data;
        if (!empty($existing_customer)) {
            // Customer already exists, retrieve the existing customer
            $stripe_customer = $existing_customer[0];
        } else {
            // Customer doesn't exist, create a new one
            $stripe_customer = \Stripe\Customer::create([
                'email' => $user->email,
            ]);
        }



        if ((($order->amount * 100) - ($order->discount * 100) + ($order->tax * 100)) <= 0) {
            return response()->json([
                "message" => "you can not pay 0",
                "order" => $order
            ], 403);
        }


        $session_data = [
            'payment_method_types' => ['card'],
            'client_reference_id' => $order->id,
            'metadata' => [
                'product_id' => '123',
                'product_description' => 'Your Service set up amount',
            ],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'GBP',
                        'product_data' => [
                            'name' => 'Your order amount',
                        ],
                        'unit_amount' => $order->amount * 100, // Amount in cents
                    ],
                    'quantity' => 1,
                ],


            ],
            'customer' => $stripe_customer,
            'mode' => 'payment',
            'success_url' => route("order.success_payment"),
            'cancel_url' => route("order.failed_payment"),
        ];

        // Add discount line item only if discount amount is greater than 0 and not null
        if (!empty($order->discount) && $order->discount > 0) {
            $session_data['line_items'][] =   [
                'price_data' => [
                    'currency' => 'GBP',
                    'product_data' => [
                        'name' => 'Discount', // Name of the discount
                    ],
                    'unit_amount' => - ($order->discount * 100), // Negative value to represent discount
                    'quantity' => 1,
                ],
            ];
        }

        // Add tax line item only if discount amount is greater than 0 and not null
        if (!empty($order->tax) && $order->tax > 0) {
            $session_data['line_items'][] =   [
                'price_data' => [
                    'currency' => 'GBP',
                    'product_data' => [
                        'name' => 'Discount', // Name of the discount
                    ],
                    'unit_amount' => ($order->tax * 100), // Negative value to represent discount
                    'quantity' => 1,
                ],
            ];
        }



        $session = Session::create($session_data);



        return redirect()->to($session->url);
    }


    public function stripePaymentSuccess(Request $request)
    {
        return redirect()->away(env("FRONT_END_URL") . '/customer/orders');
    }
    public function stripePaymentFailed(Request $request)
    {
        return redirect()->away(env("FRONT_END_URL") . '/customer/orders');
    }
}
