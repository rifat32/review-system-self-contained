<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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
     * @param string $title
     * @param string $messageBody
     * @param float|int|null $rating
     * @param string|null $businessName
     * @param string|null $userName
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
        // RESOLVE DISPLAY VARIABLES
        $user_name = trim($this->userName ?? '') ?: 'Valued Customer';
        $business_name = trim($this->businessName ?? '') ?: 'Your Business';
        $app_name = config('app.name', 'FeedGenius');
        $dashboard_url = env('FRONT_END_DASHBOARD_URL', env('FRONT_END_URL', 'http://localhost:3000')) . '/reviews';

        // RETURN REVIEW NOTIFICATION MAIL VIEW
        return $this->subject(subject: $this->title)
            ->view(view: 'mail.review_notification', data: [
                'title' => $this->title,
                'messageBody' => $this->messageBody,
                'rating' => $this->rating,
                'businessName' => $business_name,
                'userName' => $user_name,
                'appName' => $app_name,
                'dashboardUrl' => $dashboard_url
            ]);
    }
}
