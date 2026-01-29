<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountCreateMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $verificationUrl)
    {
        $this->user = $user;
        $this->verificationUrl = $verificationUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Welcome to ' . config('app.name'))
                    ->view('mail.account_created')
                    ->with([
                        'user_email' => $this->user->email,
                        'verification_url' => $this->verificationUrl,
                    ]);
    }
}
