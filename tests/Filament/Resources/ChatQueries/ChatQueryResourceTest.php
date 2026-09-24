<?php

namespace Tests\Filament\Resources\ChatQueries;

use App\Enums\FaqMatchOutcome;
use App\Filament\Resources\ChatQueries\ChatQueryResource;
use App\Filament\Resources\ChatQueries\Pages\ManageChatQueries;
use App\Models\ChatQuery;
use App\Models\FaqItem;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ChatQueryResource::class)]
class ChatQueryResourceTest extends TestCase
{
    #[Test]
    public function it_lists_chat_queries_for_editors(): void
    {
        $this->actingAs(User::factory()->create());
        $queries = ChatQuery::factory()->count(2)->create();

        Livewire::test(ManageChatQueries::class)->assertCanSeeTableRecords($queries);
    }

    #[Test]
    public function it_does_not_register_an_edit_action(): void
    {
        $this->actingAs(User::factory()->create());
        ChatQuery::factory()->create();

        $actions = Livewire::test(ManageChatQueries::class)->instance()->getTable()->getFlatRecordActions();

        $this->assertArrayNotHasKey('edit', $actions);
    }

    #[Test]
    public function it_does_not_delete_when_a_row_is_clicked(): void
    {
        $this->actingAs(User::factory()->create());
        $query = ChatQuery::factory()->create();

        $table = Livewire::test(ManageChatQueries::class)->instance()->getTable();

        $this->assertNull($table->getRecordAction($query));
    }

    #[Test]
    public function it_filters_by_outcome(): void
    {
        $this->actingAs(User::factory()->create());
        $noMatch = ChatQuery::factory()->create(['outcome' => FaqMatchOutcome::NoMatch]);
        $confident = ChatQuery::factory()->create(['outcome' => FaqMatchOutcome::Confident]);

        Livewire::test(ManageChatQueries::class)
            ->filterTable('outcome', FaqMatchOutcome::Confident->value)
            ->assertCanSeeTableRecords([$confident])
            ->assertCanNotSeeTableRecords([$noMatch]);
    }

    #[Test]
    public function it_shows_the_matched_faq_question(): void
    {
        $this->actingAs(User::factory()->create());
        $faq = FaqItem::factory()->create(['question' => 'Do you provide insurance?']);
        ChatQuery::factory()->create(['matched_faq_id' => $faq->id, 'outcome' => FaqMatchOutcome::Confident]);

        // The table defaults to filtering for outcome=no_match (surfacing
        // gaps to curate is the whole point of this resource), and a
        // matched_faq_id only ever exists on a non-no_match row -- so this
        // test has to clear that default filter to see its own fixture.
        Livewire::test(ManageChatQueries::class)
            ->filterTable('outcome', null)
            ->assertSee('Do you provide insurance?');
    }
}
