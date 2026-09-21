<?php

namespace Tests\Filament\Resources;

use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Filament\Resources\Prospects\ProspectResource;
use App\Models\Prospect;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Generator;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectResource::class)]
class ProspectResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public static function roleProvider(): Generator
    {
        yield 'admin' => [true, 200];
        yield 'editor' => [false, 403];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function it_restricts_the_leads_page_to_admins(bool $isAdmin, int $expectedStatus): void
    {
        $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();

        $response = $this->actingAs($user)->get(ProspectResource::getUrl('index'));

        $response->assertStatus($expectedStatus);
    }

    #[Test]
    public function it_lists_prospects_for_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospects = Prospect::factory()->count(2)->create();

        Livewire::test(ManageProspects::class)->assertCanSeeTableRecords($prospects);
    }

    #[Test]
    public function it_defaults_to_answer_reviews_then_human_reviews_then_newest_other_records(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $newerAnswerReview = Prospect::factory()->draftReady()->create(['submitted_at' => now()->subHour()]);
        $olderAnswerReview = Prospect::factory()->draftReady()->create(['submitted_at' => now()->subDays(2)]);
        $newerHumanReview = Prospect::factory()->pendingReview()->create(['submitted_at' => now()]);
        $olderHumanReview = Prospect::factory()->pendingReview()->create(['submitted_at' => now()->subDays(3)]);
        $newerOther = Prospect::factory()->create([
            'status' => ProspectStatus::Replied,
            'submitted_at' => now()->subMinutes(30),
        ]);
        $olderOther = Prospect::factory()->create([
            'status' => ProspectStatus::Unanswerable,
            'submitted_at' => now()->subDays(4),
        ]);

        Livewire::test(ManageProspects::class)
            ->assertCanSeeTableRecords([
                $newerAnswerReview,
                $olderAnswerReview,
                $newerHumanReview,
                $olderHumanReview,
                $newerOther,
                $olderOther,
            ], inOrder: true);
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

        Livewire::test(ManageProspects::class)
            ->assertCanSeeTableRecords([$needsReview])
            ->assertCanNotSeeTableRecords([$humanSpam, $aiSpam]);
    }

    #[Test]
    public function it_places_the_review_view_and_delete_actions_after_the_data_columns(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ManageProspects::class);

        $this->assertSame(
            RecordActionsPosition::AfterColumns,
            $component->instance()->getTable()->getRecordActionsPosition(),
        );

        $actions = $component->instance()->getTable()->getFlatRecordActions();

        $this->assertSame(['review', 'view', 'delete'], array_keys($actions));
        $this->assertInstanceOf(ViewAction::class, $actions['review']);
        $this->assertInstanceOf(ViewAction::class, $actions['view']);
        $this->assertInstanceOf(DeleteAction::class, $actions['delete']);
        $this->assertSame('Review', $actions['review']->getLabel());
    }

    #[Test]
    public function it_limits_long_names_in_the_table(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ManageProspects::class);

        $this->assertSame(50, $component->instance()->getTable()->getColumn('name')->getCharacterLimit());
    }

    #[Test]
    public function it_shows_the_review_indicator_for_the_main_prospect_states(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $readyForReview = Prospect::factory()->pendingReview()->create();
        $answerReviewPending = Prospect::factory()->draftReady()->create();
        $messageSent = Prospect::factory()->create(['status' => ProspectStatus::Replied]);
        $noActionNeeded = Prospect::factory()->spam()->create();
        $classifying = Prospect::factory()->create();
        $drafting = Prospect::factory()->create(['status' => ProspectStatus::DraftGenerating]);

        $component = Livewire::test(ManageProspects::class)
            ->assertTableColumnStateSet('review_indicator', 'ready_for_review', record: $readyForReview)
            ->assertTableColumnStateSet('review_indicator', 'answer_review_pending', record: $answerReviewPending)
            ->assertTableColumnStateSet('review_indicator', 'message_sent', record: $messageSent)
            ->assertTableColumnStateSet('review_indicator', 'no_action_needed', record: $noActionNeeded)
            ->assertTableColumnStateSet('review_indicator', 'classifying', record: $classifying)
            ->assertTableColumnStateSet('review_indicator', 'drafting', record: $drafting);

        $indicator = $component->instance()->getTable()->getColumn('review_indicator');
        $this->assertSame('warning', $indicator->getColor('answer_review_pending'));
    }

    #[Test]
    public function it_keeps_the_readable_pipeline_status_column_alongside_the_indicator(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ManageProspects::class);

        $this->assertSame('Pipeline status', $component->instance()->getTable()->getColumn('status')->getLabel());
    }

    #[Test]
    public function it_filters_the_table_by_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $spam = Prospect::factory()->spam()->create();
        $pending = Prospect::factory()->create();

        Livewire::test(ManageProspects::class)
            ->filterTable('status', ProspectStatus::Spam->value)
            ->assertCanSeeTableRecords([$spam])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    #[Test]
    public function it_only_offers_human_meaningful_status_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageProspects::class)
            ->assertTableFilterExists('status', function (SelectFilter $filter): bool {
                return array_keys($filter->getOptions()) === [
                    ProspectStatus::PendingReview->value,
                    ProspectStatus::DraftReady->value,
                    ProspectStatus::Spam->value,
                    ProspectStatus::Unanswerable->value,
                    ProspectStatus::Replied->value,
                ];
            });
    }

    #[Test]
    public function it_formats_prospect_interests_for_the_view_action(): void
    {
        $this->assertSame('Wedding', ProspectResource::formatInterests('Wedding'));
        $this->assertSame('Corporate Event', ProspectResource::formatInterests('Corporate Event'));
        $this->assertSame('Wedding, Corporate Event', ProspectResource::formatInterests('["Wedding","Corporate Event"]'));
    }

    #[Test]
    public function it_distinguishes_human_review_from_the_ai_classification(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Prospect::factory()->pendingReview()->create();

        Livewire::test(ManageProspects::class)
            ->assertSee('Human review')
            ->assertSee('AI classification')
            ->assertSee('Pending')
            ->assertSee('Valid');
    }

    #[Test]
    public function it_opens_the_view_action_for_a_prospect_without_interests(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->create(['interests' => null]);
        $component = Livewire::test(ManageProspects::class);
        $reviewAction = $component->instance()->getTable()->getRecordActions()[0];

        $this->assertInstanceOf(ViewAction::class, $reviewAction);
        $this->assertSame('Review', $reviewAction->getLabel());
        $this->assertSame(Heroicon::Cog6Tooth, $reviewAction->getIcon());
        $this->assertTrue($reviewAction->isModalSlideOver());

        $component->callTableAction('review', $prospect)->assertOk();
    }

    #[Test]
    public function it_opens_the_view_action_for_a_draft_ready_prospect(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create();

        Livewire::test(ManageProspects::class)
            ->callTableAction('review', $prospect)
            ->assertOk();
    }

    #[Test]
    public function it_shows_the_active_status_spinner_in_the_open_prospect_view(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->pendingReview()->create([
            'status' => ProspectStatus::DraftGenerating,
        ]);

        Livewire::test(ManageProspects::class)
            ->callTableAction('review', $prospect)
            ->assertSee('Drafting reply')
            ->assertSeeHtml('animation: dwc-status-spin');
    }

    #[Test]
    public function it_subscribes_to_the_fixed_pipeline_channel_regardless_of_which_record_is_open(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $listeners = (new ManageProspects)->getListeners();

        $this->assertSame(['echo-private:prospects-pipeline,.status.changed' => '$refresh'], $listeners);
    }
}
