<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserPaymentSuccess extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $paymentDetails;

    /**
     * Create a new message instance.
     *
     * @param mixed $user
     * @param array $paymentDetails
     */
    public function __construct($user, array $paymentDetails = [])
    {
        $this->user = $user;
        $this->paymentDetails = $paymentDetails;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // RESOLVE USER DISPLAY NAME AND EMAIL
        $user_name = trim(($this->user->first_Name ?? '') . " " . ($this->user->middle_Name ?? '') . " " . ($this->user->last_Name ?? '')) ?: 'Valued Customer';
        $user_email = $this->user->email ?? '';

        // RESOLVE APPLICATION AND DASHBOARD URLS
        $app_name = config('app.name', 'FeedGenius');
        $dashboard_url = $this->paymentDetails['dashboard_url'] ?? (env('FRONT_END_DASHBOARD_URL', env('FRONT_END_URL', 'http://localhost:3000')) . '/dashboard');

        // PREPARE EXTRA PAYMENT DETAILS
        $amount = $this->paymentDetails['amount'] ?? null;
        $currency = strtoupper($this->paymentDetails['currency'] ?? 'USD');
        $plan_name = $this->paymentDetails['plan_name'] ?? null;
        $transaction_id = $this->paymentDetails['transaction_id'] ?? $this->paymentDetails['invoice_id'] ?? null;
        $payment_date = $this->paymentDetails['payment_date'] ?? date('F j, Y');

        // RETURN PAYMENT SUCCESS MAIL VIEW
        return $this->subject(subject: 'Payment Confirmation: Thank You for Your Purchase')
            ->view(view: 'email.user_payment_success', data: [
                'user' => $this->user,
                'userName' => $user_name,
                'userEmail' => $user_email,
                'appName' => $app_name,
                'dashboardUrl' => $dashboard_url,
                'amount' => $amount,
                'currency' => $currency,
                'planName' => $plan_name,
                'transactionId' => $transaction_id,
                'paymentDate' => $payment_date
            ]);
    }
}
