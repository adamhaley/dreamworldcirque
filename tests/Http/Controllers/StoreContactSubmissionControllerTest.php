<?php

namespace Tests\Http\Controllers;

use App\Enums\PageTemplate;
use App\Http\Controllers\StoreContactSubmissionController;
use App\Jobs\ClassifyProspect;
use App\Models\Page;
use App\Models\Prospect;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(StoreContactSubmissionController::class)]
class StoreContactSubmissionControllerTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'phone' => '555-0100',
            'message' => 'We would love a quote for a corporate gala.',
            'form_type' => 'Home Form Submission',
        ], $overrides);
    }

    #[Test]
    public function it_creates_a_prospect_with_the_home_form_type(): void
    {
        Queue::fake();

        $response = $this->postJson('/contact', $this->validPayload());

        $response->assertCreated();
        $this->assertDatabaseHas(Prospect::class, [
            'source' => 'website_contact_form',
            'form_type' => 'Home Form Submission',
            'name' => 'Jo Riggs',
            'email' => 'jo@example.com',
            'phone' => '555-0100',
        ]);
    }

    #[Test]
    public function it_creates_a_prospect_with_the_quote_form_type(): void
    {
        Queue::fake();

        $this->postJson('/contact', $this->validPayload(['form_type' => 'Get_A_Quote Form Submission']));

        $this->assertDatabaseHas(Prospect::class, ['form_type' => 'Get_A_Quote Form Submission']);
    }

    #[Test]
    public function it_rejects_an_unknown_form_type(): void
    {
        Queue::fake();

        $this->postJson('/contact', $this->validPayload(['form_type' => 'Something Else']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('form_type');
    }

    #[Test]
    public function it_stores_checked_interests_as_an_array(): void
    {
        Queue::fake();

        $this->postJson('/contact', $this->validPayload(['interests' => ['Weddings & Private Events', 'Corporate Events']]));

        $prospect = Prospect::query()->sole();
        $this->assertSame(['Weddings & Private Events', 'Corporate Events'], $prospect->interests);
    }

    #[Test]
    public function it_stores_no_interests_as_null(): void
    {
        Queue::fake();

        $this->postJson('/contact', $this->validPayload());

        $this->assertNull(Prospect::query()->sole()->interests);
    }

    #[Test]
    public function it_dispatches_the_classification_job(): void
    {
        Queue::fake();

        $this->postJson('/contact', $this->validPayload());

        $prospect = Prospect::query()->sole();
        Queue::assertPushed(ClassifyProspect::class, fn (ClassifyProspect $job): bool => $job->prospectId === $prospect->id);
    }

    #[Test]
    public function it_returns_json_validation_errors_for_a_missing_email(): void
    {
        Queue::fake();

        $this->postJson('/contact', $this->validPayload(['email' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_rejects_a_filled_honeypot_without_creating_a_prospect(): void
    {
        Queue::fake();

        $this->postJson('/contact', $this->validPayload(['website' => 'https://spam.example']))
            ->assertUnprocessable();

        $this->assertDatabaseCount(Prospect::class, 0);
    }

    #[Test]
    public function it_redirects_back_with_a_status_for_a_non_ajax_submission(): void
    {
        Queue::fake();

        $response = $this->from('/contact')->post('/contact', $this->validPayload());

        $response->assertRedirect('/contact');
        $response->assertSessionHas('status');
    }

    #[Test]
    public function it_throttles_repeat_submissions(): void
    {
        Queue::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/contact', $this->validPayload(['email' => "jo{$i}@example.com"]))->assertCreated();
        }

        $this->postJson('/contact', $this->validPayload(['email' => 'jo-last@example.com']))->assertStatus(429);
    }

    #[Test]
    public function it_renders_interest_checkboxes_from_live_service_pages(): void
    {
        Page::factory()->create(['template' => PageTemplate::Service, 'title' => 'Aerial Bartending', 'sort_order' => 1]);
        Page::factory()->create(['template' => PageTemplate::Contact, 'slug' => 'contact']);

        $response = $this->get('/contact');

        $response->assertSee('Aerial Bartending');
    }
}
