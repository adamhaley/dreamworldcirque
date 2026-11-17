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
            // Sent as jena@ (MAIL_USERNAME and MAIL_FROM_ADDRESS must match per
            // Exim's anti-spoofing policy): a real mailbox, so the From line
            // invites a reply instead of saying noreply. A forwarder on the
            // same address routes replies to her Gmail; Reply-To pins it here
            // so replies still reach her if the env sender ever changes.
            ->replyTo('jena@dreamworldcirque.com', 'Dream World Cirque')
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
