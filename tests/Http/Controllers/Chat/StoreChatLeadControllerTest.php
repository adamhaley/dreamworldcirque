<?php

namespace Tests\Http\Controllers\Chat;

use App\Enums\ProspectStatus;
use App\Http\Controllers\Chat\StoreChatLeadController;
use App\Jobs\ClassifyProspect;
use App\Models\ChatQuery;
use App\Models\Prospect;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(StoreChatLeadController::class)]
class StoreChatLeadControllerTest extends TestCase
{
    #[Test]
    public function it_creates_a_chatbot_prospect_from_the_unanswered_question(): void
    {
        Queue::fake();
        Notification::fake();

        $response = $this->postJson('/chat/lead', [
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'question' => 'Do you do fire performances indoors?',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas(Prospect::class, [
            'source' => 'chatbot',
            'form_type' => 'Chat Fallback',
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'message' => 'Do you do fire performances indoors?',
            'status' => ProspectStatus::PendingClassification->value,
        ]);
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_stores_an_optional_phone_number(): void
    {
        Queue::fake();

        $this->postJson('/chat/lead', [
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'phone' => '555-123-4567',
            'question' => 'Do you do fire performances indoors?',
        ])->assertCreated();

        $this->assertDatabaseHas(Prospect::class, [
            'email' => 'jo@example.com',
            'phone' => '555-123-4567',
        ]);
    }

    #[Test]
    public function it_dispatches_the_classification_job(): void
    {
        Queue::fake();

        $this->postJson('/chat/lead', [
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'question' => 'Do you do fire performances indoors?',
        ]);

        $prospect = Prospect::query()->sole();
        Queue::assertPushed(ClassifyProspect::class, fn (ClassifyProspect $job): bool => $job->prospectId === $prospect->id);
    }

    #[Test]
    public function it_links_the_prospect_to_its_chat_query_log(): void
    {
        Queue::fake();
        $log = ChatQuery::factory()->create(['question' => 'Do you do fire performances indoors?']);

        $this->postJson('/chat/lead', [
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'question' => 'Do you do fire performances indoors?',
        ]);

        $prospect = Prospect::query()->sole();
        $this->assertSame($prospect->id, $log->fresh()->prospect_id);
    }

    #[Test]
    public function it_requires_a_name(): void
    {
        Queue::fake();

        $this->postJson('/chat/lead', ['email' => 'jo@example.com', 'question' => 'A question'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_requires_a_valid_email(): void
    {
        Queue::fake();

        $this->postJson('/chat/lead', ['name' => 'Jo Riggs', 'email' => 'not-an-email', 'question' => 'A question'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_rejects_a_filled_honeypot_without_creating_a_prospect(): void
    {
        Queue::fake();

        $this->postJson('/chat/lead', [
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'question' => 'A question',
            'website' => 'https://spam.example',
        ])->assertUnprocessable();

        $this->assertDatabaseCount(Prospect::class, 0);
    }

    #[Test]
    public function it_throttles_excessive_requests(): void
    {
        Queue::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/chat/lead', [
                'name' => 'Jo Riggs',
                'email' => 'jo@example.com',
                'question' => "Question {$i}",
            ])->assertCreated();
        }

        $this->postJson('/chat/lead', [
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'question' => 'One more',
        ])->assertStatus(429);
    }
}
