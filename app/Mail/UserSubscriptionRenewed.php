<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserSubscriptionRenewed extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $subscription;

    /**
     * Create a new message instance.
     *
     * @param mixed $user
     * @param mixed $subscription
     */
    public function __construct($user, $subscription)
    {
        $this->user = $user;
        $this->subscription = $subscription;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // RESOLVE BUSINESS AND USER DISPLAY NAMES
        $business = $this->user->business ?? null;
        $business_name = $business->Name ?? $business->name ?? 'Your Business';
        $user_name = trim(($this->user->first_Name ?? '') . " " . ($this->user->middle_Name ?? '') . " " . ($this->user->last_Name ?? '')) ?: 'Valued Customer';
        $user_email = $this->user->email ?? '';

        // RESOLVE APP AND DASHBOARD URLS
        $app_name = config('app.name', 'FeedGenius');
        $dashboard_url = env('FRONT_END_DASHBOARD_URL', env('FRONT_END_URL', 'http://localhost:3000')) . '/dashboard';

        // PREPARE RENEWAL DETAILS
        $plan_name = $this->subscription->service_plan->name ?? 'Subscription Plan';
        $amount = $this->subscription->amount ?? null;
        $end_date = isset($this->subscription->end_date) ? (is_string($this->subscription->end_date) ? date('F j, Y', strtotime($this->subscription->end_date)) : $this->subscription->end_date->format('F j, Y')) : date('F j, Y');
        $transaction_id = $this->subscription->transaction_id ?? null;

        // RETURN SUBSCRIPTION RENEWED MAIL VIEW
        return $this->subject(subject: "Subscription Renewed: " . $business_name)
            ->view(view: 'email.user_subscription_renewed', data: [
                'user' => $this->user,
                'subscription' => $this->subscription,
                'userName' => $user_name,
                'userEmail' => $user_email,
                'businessName' => $business_name,
                'appName' => $app_name,
                'dashboardUrl' => $dashboard_url,
                'planName' => $plan_name,
                'amount' => $amount,
                'endDate' => $end_date,
                'transactionId' => $transaction_id
            ]);
    }
}
