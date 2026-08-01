<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $token;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // GENERATE FORGOT PASSWORD RESET LINK
        $reset_url = env('FRONT_END_DASHBOARD_URL', 'http://localhost:3000') . '/auth/change-password?token=' . $this->token;

        // RESOLVE APPLICATION AND USER DISPLAY NAMES
        $app_name = config('app.name', 'FeedGenius');
        $user_name = trim(($this->user->first_Name ?? '') . ' ' . ($this->user->last_Name ?? '')) ?: 'User';

        // RESOLVE BUSINESS LOGO FULL URL USING APP_URL
        $logo_url = null;
        $raw_logo = $this->user->business->Logo ?? $this->user->business->logo ?? $this->user->logo ?? null;

        if (!empty($raw_logo)) {
            if (preg_match('/^https?:\/\//i', $raw_logo)) {
                $logo_url = $raw_logo;
            } else {
                $app_url = rtrim(env('APP_URL', config('app.url', 'http://localhost')), '/');
                $logo_url = $app_url . '/' . ltrim($raw_logo, '/');
            }
        }


        // RETURN BLADE RESET PASSWORD MAIL VIEW WITH PREPARED DATA
        return $this->subject(subject: 'Reset Your Password')
            ->view(view: 'mail.reset_password', data: [
                "user" => $this->user,
                "token" => $this->token,
                "user_name" => $user_name,
                "app_name" => $app_name,
                "reset_url" => $reset_url,
                "logo_url" => $logo_url
            ]);
    }
}
