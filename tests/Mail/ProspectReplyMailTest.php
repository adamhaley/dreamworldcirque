<?php

namespace Tests\Mail;

use App\Mail\ProspectReplyMail;
use App\Models\Prospect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectReplyMail::class)]
class ProspectReplyMailTest extends TestCase
{
    #[Test]
    public function it_replies_to_the_monitored_inbox_instead_of_the_authenticated_sending_account(): void
    {
        $mail = new ProspectReplyMail(Prospect::factory()->draftReady()->make());

        // MAIL_FROM_ADDRESS must be a dedicated sending-only mailbox (Exim
        // requires the authenticated account and the From address to match),
        // not the address Jena actually reads -- this is what routes a
        // customer's reply to the inbox that forwards to her Gmail instead.
        $mail->assertHasReplyTo('info@dreamworldcirque.com');
    }

    #[Test]
    public function it_has_the_expected_subject(): void
    {
        $mail = new ProspectReplyMail(Prospect::factory()->draftReady()->make());

        $mail->assertHasSubject('Re: Your inquiry to Dream World Cirque');
    }
}
