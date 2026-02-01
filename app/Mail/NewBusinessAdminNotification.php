<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewBusinessAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $business;
    public $planName;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $business, $planName)
    {
        $this->user = $user;
        $this->business = $business;
        $this->planName = $planName;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this
            ->subject("New Business Registered: " . ($this->business->Name ?? 'N/A'))
            ->view('email.admin_business_registered');
    }
}
