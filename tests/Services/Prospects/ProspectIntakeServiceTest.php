<?php

namespace Tests\Services\Prospects;

use App\Enums\ProspectStatus;
use App\Jobs\ClassifyProspect;
use App\Services\Prospects\ProspectIntakeData;
use App\Services\Prospects\ProspectIntakeService;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectIntakeService::class)]
class ProspectIntakeServiceTest extends TestCase
{
    #[Test]
    public function it_creates_a_pending_classification_prospect(): void
    {
        Queue::fake();

        $prospect = app(ProspectIntakeService::class)->record('chatbot', 'Chat Fallback', new ProspectIntakeData(
            name: 'Jo Riggs',
            email: 'jo@example.com',
            message: 'Do you do fire performances?',
        ));

        $this->assertSame('chatbot', $prospect->source);
        $this->assertSame('Chat Fallback', $prospect->form_type);
        $this->assertSame(ProspectStatus::PendingClassification, $prospect->status);
        $this->assertNotNull($prospect->submitted_at);
    }

    #[Test]
    public function it_dispatches_classification_on_creation(): void
    {
        Queue::fake();

        $prospect = app(ProspectIntakeService::class)->record('chatbot', 'Chat Fallback', new ProspectIntakeData(
            email: 'jo@example.com',
        ));

        Queue::assertPushed(ClassifyProspect::class, fn (ClassifyProspect $job): bool => $job->prospectId === $prospect->id);
    }

    #[Test]
    public function it_normalizes_an_empty_interests_array_to_null(): void
    {
        Queue::fake();

        $prospect = app(ProspectIntakeService::class)->record('website_contact_form', 'Home Form Submission', new ProspectIntakeData(
            email: 'jo@example.com',
            interests: [],
        ));

        $this->assertNull($prospect->interests);
    }

    #[Test]
    public function it_stores_provided_interests(): void
    {
        Queue::fake();

        $prospect = app(ProspectIntakeService::class)->record('website_contact_form', 'Home Form Submission', new ProspectIntakeData(
            email: 'jo@example.com',
            interests: ['Wedding', 'Corporate Event'],
        ));

        $this->assertSame(['Wedding', 'Corporate Event'], $prospect->interests);
    }

    #[Test]
    public function it_stores_the_raw_payload(): void
    {
        Queue::fake();

        $prospect = app(ProspectIntakeService::class)->record('chatbot', 'Chat Fallback', new ProspectIntakeData(
            email: 'jo@example.com',
            rawPayload: ['unmatched_question' => 'do you do fire', 'top_score' => 0.12],
        ));

        $this->assertSame(['unmatched_question' => 'do you do fire', 'top_score' => 0.12], $prospect->raw_payload);
    }

    #[Test]
    public function it_allows_multiple_prospects_without_a_source_external_id(): void
    {
        Queue::fake();
        $service = app(ProspectIntakeService::class);

        $service->record('chatbot', 'Chat Fallback', new ProspectIntakeData(email: 'a@example.com'));
        $second = $service->record('chatbot', 'Chat Fallback', new ProspectIntakeData(email: 'b@example.com'));

        $this->assertNotNull($second->id);
    }
}
