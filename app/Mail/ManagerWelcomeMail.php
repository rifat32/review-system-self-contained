<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ManagerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;
    public $businessName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $password, $businessName)
    {
        $this->user = $user;
        $this->password = $password;
        $this->businessName = $businessName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $roleName = ucwords(str_replace('_', ' ', $this->user->roles->first()->name ?? 'Staff'));

        return $this->view('mail.manager_welcome', [
            'userName' => $this->user->first_Name . " " . $this->user->last_Name,
            'email' => $this->user->email,
            'password' => $this->password,
            'businessName' => $this->businessName,
            'role' => $roleName,
            'loginUrl' => env('FRONT_END_DASHBOARD_URL') . '/user/login'
        ])->subject('Welcome to ' . $this->businessName . ' - ' . $roleName . ' Account Created');
    }
}
