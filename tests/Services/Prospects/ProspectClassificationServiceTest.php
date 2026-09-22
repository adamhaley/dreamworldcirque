<?php

namespace Tests\Services\Prospects;

use App\Ai\Agents\ProspectClassifier;
use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Prospects\ProspectClassificationService;
use App\Services\Prospects\ProspectEmailDomainChecker;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectClassificationService::class)]
class ProspectClassificationServiceTest extends TestCase
{
    #[Test]
    public function it_includes_the_raw_email_and_domain_check_result_in_the_prompt(): void
    {
        Queue::fake();
        $this->mock(ProspectEmailDomainChecker::class)
            ->shouldReceive('hasMailExchanger')
            ->with('joriggsvideo2@gmail.com')
            ->andReturn(true);
        ProspectClassifier::fake(['classification' => 'valid', 'confidence' => 70, 'reason' => 'Plausible name in a legitimate provider address.']);
        $prospect = Prospect::factory()->create([
            'name' => 'Jo Riggs',
            'email' => 'joriggsvideo2@gmail.com',
            'message' => null,
        ]);

        app(ProspectClassificationService::class)->classify($prospect);

        ProspectClassifier::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Email: joriggsvideo2@gmail.com')
            && str_contains($prompt->prompt, 'Email domain accepts mail: yes'));
    }

    #[Test]
    public function it_stores_the_email_domain_check_result_on_the_prospect(): void
    {
        Queue::fake();
        $this->mock(ProspectEmailDomainChecker::class)
            ->shouldReceive('hasMailExchanger')
            ->andReturn(false);
        ProspectClassifier::fake(['classification' => 'spam', 'confidence' => 60, 'reason' => 'No mail servers for this domain.']);
        $prospect = Prospect::factory()->create(['email' => 'nobody@this-domain-does-not-exist-dwc-test.invalid']);

        app(ProspectClassificationService::class)->classify($prospect);

        $this->assertFalse($prospect->refresh()->email_mx_valid);
    }

    #[Test]
    public function it_skips_the_domain_check_and_records_no_email_given_when_email_is_null(): void
    {
        Queue::fake();
        $this->mock(ProspectEmailDomainChecker::class)->shouldNotReceive('hasMailExchanger');
        ProspectClassifier::fake(['classification' => 'needs_human', 'confidence' => 10, 'reason' => 'No contact information given.']);
        $prospect = Prospect::factory()->create(['email' => null]);

        app(ProspectClassificationService::class)->classify($prospect);

        $this->assertNull($prospect->refresh()->email_mx_valid);
        ProspectClassifier::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Email domain accepts mail: no email given'));
    }

    #[Test]
    public function it_includes_name_and_email_from_a_reviewed_example_with_no_message(): void
    {
        Queue::fake();
        $this->mock(ProspectEmailDomainChecker::class)
            ->shouldReceive('hasMailExchanger')
            ->andReturn(true);
        Prospect::factory()->create([
            'name' => 'Jo Riggs',
            'email' => 'joriggsvideo2@gmail.com',
            'message' => null,
            'status' => ProspectStatus::Valid,
            'classification' => ProspectClassification::Valid,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory()->admin()->create()->id,
        ]);
        ProspectClassifier::fake(['classification' => 'valid', 'confidence' => 70, 'reason' => 'Matches a prior reviewed example.']);
        $prospect = Prospect::factory()->create(['name' => 'Alex Chen', 'email' => 'alexchen99@gmail.com', 'message' => null]);

        app(ProspectClassificationService::class)->classify($prospect);

        ProspectClassifier::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Name: Jo Riggs')
            && str_contains($prompt->prompt, 'Email: joriggsvideo2@gmail.com'));
    }
}
