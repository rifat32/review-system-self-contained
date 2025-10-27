<?php

namespace App\Http\Controllers;


use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;

class CustomWebhookController extends WebhookController
{
    public function handleStripeWebhook(Request $request)
    {
        // Retrieve the event data from the request body
        $payload = $request->all();

        // Log the entire payload for debugging purposes
        Log::info('Webhook Payload: ' . json_encode($payload));

        // Extract the event type
        $eventType = $payload['type'] ?? null;
        return response()->json(['message' => 'Webhook received']);
        // Log the event type
        Log::info('Event Type: ' . $eventType);

        // Handle the event based on its type
        if ($eventType === 'checkout.session.completed') {
            $this->handleChargeSucceeded($payload['data']['object']);
        }

        // Return a response to Stripe to acknowledge receipt of the webhook
        return response()->json(['message' => 'Webhook received']);
    }

    /**
     * Handle payment succeeded webhook from Stripe.
     *
     * @param  array  $paymentCharge
     * @return void
     */
    protected function handleChargeSucceeded($paymentCharge)
    {
     // Extract required data from payment charge
$amount = $paymentCharge['amount'] ?? null;
$currency = $paymentCharge['currency'] ?? null;
$customerID = $paymentCharge['customer'] ?? null;

// Assuming you're using the `checkout.session.completed` event:
if (isset($paymentCharge['client_reference_id'])) {
    $orderId = $paymentCharge['client_reference_id'];
} else {
    // Handle the case where the order ID is missing (log error, notify admin)
    error_log("Order ID not found in Stripe webhook data.");
    // ... additional handling based on your application logic
    return; // or throw an exception
}

$user = User::where("stripe_id",$customerID)->first();
$order = Order::where([
    "id" => $orderId,
])
->first();

if (!$order) {
    // Handle the case where the order is not found (log error)
    error_log("Order with ID $orderId not found.");
    return; // or throw an exception
}

// Update order data
$order->card = $amount; // Assuming "card" represents the order amount
$order->payment_method = "card";

// Add more fields as needed (example: status update)
$order->status = "paid"; // Example status update

$order->save();

    }



}
