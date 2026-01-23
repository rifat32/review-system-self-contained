<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserPaymentFailed extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $user_name = trim($this->user->first_Name . " " . ($this->user->middle_Name ?? '') . " " . $this->user->last_Name);

        return $this
            ->subject("Payment Failed Alert")
            ->view('email.user_payment_failed', [
                'userName' => $user_name,
                'userEmail' => $this->user->email
            ]);
    }
}
