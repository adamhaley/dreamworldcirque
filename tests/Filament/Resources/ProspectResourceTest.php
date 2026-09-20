<?php

namespace Tests\Filament\Resources;

use App\Enums\ProspectStatus;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Filament\Resources\Prospects\ProspectResource;
use App\Models\Prospect;
use App\Models\User;
use Filament\Actions\ViewAction;
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
    public function it_opens_the_view_action_for_a_prospect_with_interests(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->create(['interests' => ['Wedding', 'Corporate Event']]);

        Livewire::test(ManageProspects::class)
            ->callTableAction(ViewAction::class, $prospect)
            ->assertOk();
    }

    #[Test]
    public function it_opens_the_view_action_for_a_prospect_without_interests(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->create(['interests' => null]);

        Livewire::test(ManageProspects::class)
            ->callTableAction(ViewAction::class, $prospect)
            ->assertOk();
    }

    #[Test]
    public function it_opens_the_view_action_for_a_draft_ready_prospect(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create();

        Livewire::test(ManageProspects::class)
            ->callTableAction(ViewAction::class, $prospect)
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
            ->callTableAction(ViewAction::class, $prospect)
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
