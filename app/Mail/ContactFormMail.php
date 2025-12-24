<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     * @param array $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $fromAddress = config('mail.from.address') ?: 'rony.mia7800@gmail.com'; // fallback
        $fromName    = config('mail.from.name') ?: config('app.name', 'My App');

        return $this->from($fromAddress, $fromName)
            ->replyTo(
                $this->data['email'],
                trim(($this->data['first_name'] ?? '') . ' ' . ($this->data['last_name'] ?? ''))
            )
            ->subject('New Contact Message: ' . ($this->data['subject'] ?? ''))
            ->view('mail.contact_template')
            ->with(['data' => $this->data]);
    }
}
