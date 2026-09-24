<?php

namespace Tests\Filament\Widgets;

use App\Enums\ProspectClassification;
use App\Filament\Widgets\LatestProspectsTable;
use App\Models\Prospect;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(LatestProspectsTable::class)]
class LatestProspectsTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public static function roleProvider(): Generator
    {
        yield 'admin' => [true, true];
        yield 'editor' => [false, false];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function it_restricts_the_latest_prospects_table_to_admins(bool $isAdmin, bool $expected): void
    {
        $this->actingAs($isAdmin ? User::factory()->admin()->create() : User::factory()->create());

        $this->assertSame($expected, LatestProspectsTable::canView());
    }

    #[Test]
    public function it_lists_only_the_five_most_recently_submitted_prospects(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $newest = Prospect::factory()->count(5)->create(['submitted_at' => now()]);
        $oldest = Prospect::factory()->create(['submitted_at' => now()->subYear()]);

        Livewire::test(LatestProspectsTable::class)
            ->assertCanSeeTableRecords($newest)
            ->assertCanNotSeeTableRecords([$oldest]);
    }

    #[Test]
    public function it_hides_a_prospect_when_either_ai_or_human_classifies_it_as_spam(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $humanSpam = Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'human_classification' => ProspectClassification::Spam,
        ]);
        $aiSpam = Prospect::factory()->create([
            'classification' => ProspectClassification::Spam,
            'human_classification' => null,
        ]);
        $needsReview = Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'human_classification' => null,
        ]);

        Livewire::test(LatestProspectsTable::class)
            ->assertCanSeeTableRecords([$needsReview])
            ->assertCanNotSeeTableRecords([$humanSpam, $aiSpam]);
    }
}
