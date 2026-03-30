<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\ReviewNew;
use App\Models\Business;
use App\Models\User;

class ReviewNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $messageBody;
    public $rating;
    public $businessName;
    public $userName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($title, $messageBody, $rating = null, $businessName = null, $userName = null)
    {
        $this->title = $title;
        $this->messageBody = $messageBody;
        $this->rating = $rating;
        $this->businessName = $businessName;
        $this->userName = $userName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->title)
                    ->view('mail.review_notification');
    }
}

