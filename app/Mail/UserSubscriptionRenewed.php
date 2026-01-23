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
     */
    public function __construct($user, $subscription)
    {
        $this->user = $user;
        $this->subscription = $subscription;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $business = $this->user->business;
        $user_name = trim($this->user->first_Name . " " . ($this->user->middle_Name ?? '') . " " . $this->user->last_Name);

        return $this
            ->subject("Subscription Renewed: " . ($business->Name ?? 'N/A'))
            ->view('email.user_subscription_renewed', [
                'userName' => $user_name,
                'businessName' => $business->Name ?? 'N/A',
                'planName' => $this->subscription->service_plan->name ?? 'N/A',
                'amount' => $this->subscription->amount,
                'endDate' => $this->subscription->end_date->format('Y-m-d'),
                'subscription' => $this->subscription
            ]);
    }
}
