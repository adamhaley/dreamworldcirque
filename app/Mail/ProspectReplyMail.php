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
            // The account authenticating to send this (see MAIL_USERNAME) must
            // match MAIL_FROM_ADDRESS exactly, per Exim's anti-spoofing policy
            // -- that's a dedicated sending-only mailbox, not the inbox Jena
            // actually reads. Reply-To sends any customer reply to the real
            // address instead, which already forwards to her Gmail.
            ->replyTo('info@dreamworldcirque.com', 'Dream World Cirque')
            // BCC the same inbox so Jena keeps a copy of everything sent from
            // the admin (it forwards to her Gmail).
            ->bcc('info@dreamworldcirque.com')
            ->text('emails.prospect-reply');
    }
}
