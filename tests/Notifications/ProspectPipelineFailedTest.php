<?php

namespace Tests\Notifications;

use App\Models\Prospect;
use App\Notifications\ProspectPipelineFailed;
use Illuminate\Notifications\AnonymousNotifiable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectPipelineFailed::class)]
class ProspectPipelineFailedTest extends TestCase
{
    #[Test]
    public function it_names_the_prospect_and_stage_and_links_to_the_review(): void
    {
        $prospect = Prospect::factory()->make(['id' => 42, 'name' => 'Jo Riggs']);

        $mail = (new ProspectPipelineFailed($prospect, 'classification', 'Your API key has expired.'))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame('Dream World Cirque prospect classification failed: Jo Riggs', $mail->subject);
        $this->assertContains('Reason: Your API key has expired.', $mail->introLines);
        $this->assertStringContainsString('/admin/prospects?tableAction=review&tableActionRecord=42', $mail->actionUrl);
    }
}
