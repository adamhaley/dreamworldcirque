<?php

namespace App\Mail;

use App\Models\Prospect;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProspectReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Prospect $prospect) {}

    public function build(): self
    {
        return $this
            ->subject('Re: Your inquiry to Dream World Cirque')
            ->text('emails.prospect-reply');
    }
}
