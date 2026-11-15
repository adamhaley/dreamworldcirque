<?php

namespace App\Mail;

use App\Models\Prospect;
use App\Services\Prospects\ProspectReplyAttachmentData;
use App\Services\Prospects\ProspectReplyAttachmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
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

    /**
     * Resized JPEG copies of the images saved on the prospect; originals are never attached.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $entries = $this->prospect->reply_attachments;

        if ($entries === null || $entries === []) {
            return [];
        }

        $prepared = app(ProspectReplyAttachmentService::class)->prepare(
            array_map(ProspectReplyAttachmentData::fromArray(...), array_values($entries)),
        );

        return array_map(
            fn ($copy): Attachment => Attachment::fromData(fn (): string => $copy->contents, $copy->filename)
                ->withMime($copy->mime),
            $prepared,
        );
    }
}
