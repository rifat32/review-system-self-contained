<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GuestUserReviewReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $pdfContents;
    public $filename;

    public function __construct($pdfContents, $filename)
    {
        $this->pdfContents = $pdfContents;
        $this->filename = $filename;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('guest-user-review-report-mail')
                    ->attachData($this->pdfContents, $this->filename);
    }
}
