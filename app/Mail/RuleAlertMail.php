<?php

namespace App\Mail;

use App\Models\{AiRule, ReviewNew};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RuleAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public AiRule $rule;
    public ReviewNew $review;

    public function __construct(AiRule $rule, ReviewNew $review)
    {
        $this->rule = $rule;
        $this->review = $review;
    }

    public function build()
    {
        return $this->subject("AI Rule Alert: {$this->rule->rule_name}")
            ->html("<p>Review #{$this->review->id} triggered rule: <strong>{$this->rule->rule_name}</strong></p><p>Review Comment: {$this->review->comment}</p>");
    }
}
