<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRegistered extends Mailable
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
            ->subject("New Business Alert: " . ($business->Name ?? 'N/A') . " registered")
            ->view('email.user_registered', [
                'userName' => $user_name,
                'userEmail' => $this->user->email,
                'registrationDate' => $this->user->created_at->format('Y-m-d'),
                'businessName' => $business->Name ?? 'N/A',
                'subscriptionName' => $this->subscription->service_plan->name ?? 'N/A',
                'subscription' => $this->subscription
            ]);
    }
}
