<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateWrapper;
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
        // Try to get custom email template
        $email_content = EmailTemplate::where([
            "type" => "manager_welcome_mail",
            "is_active" => 1
        ])->first();

        // If no custom template, use default
        if (!$email_content) {
            return $this->buildDefaultTemplate();
        }

        $html_content = json_decode($email_content->template);
        $html_content = str_replace("[FirstName]", $this->user->first_Name, $html_content);
        $html_content = str_replace("[LastName]", $this->user->last_Name, $html_content);
        $html_content = str_replace("[FullName]", ($this->user->first_Name . " " . $this->user->last_Name), $html_content);
        $html_content = str_replace("[Email]", $this->user->email, $html_content);
        $html_content = str_replace("[Password]", $this->password, $html_content);
        $html_content = str_replace("[BusinessName]", $this->businessName, $html_content);
        $html_content = str_replace("[Role]", ucwords(str_replace('_', ' ', $this->user->roles->first()->name ?? 'Staff')), $html_content);
        $html_content = str_replace("[LoginUrl]", env('FRONT_END_URL') . '/login', $html_content);

        $email_template_wrapper = EmailTemplateWrapper::where([
            "id" => $email_content->wrapper_id
        ])->first();

        if ($email_template_wrapper) {
            $html_final = json_decode($email_template_wrapper->template);
            $html_final = str_replace("[content]", $html_content, $html_final);
        } else {
            $html_final = $html_content;
        }

        return $this->view('mail.dynamic_mail', ["html_content" => $html_final])
            ->subject('Welcome to ' . $this->businessName);
    }

    /**
     * Build default template when no custom template exists
     */
    private function buildDefaultTemplate()
    {
        $roleName = ucwords(str_replace('_', ' ', $this->user->roles->first()->name ?? 'Staff'));

        return $this->view('mail.manager_welcome', [
            'userName' => $this->user->first_Name . " " . $this->user->last_Name,
            'email' => $this->user->email,
            'password' => $this->password,
            'businessName' => $this->businessName,
            'role' => $roleName,
            'loginUrl' => env('FRONT_END_URL') . '/login'
        ])->subject('Welcome to ' . $this->businessName . ' - ' . $roleName . ' Account Created');
    }
}
