<?php

namespace Tests\Filament\Resources\ReplyExamples;

use App\Filament\Resources\ReplyExamples\Pages\ManageReplyExamples;
use App\Filament\Resources\ReplyExamples\ReplyExampleResource;
use App\Models\ReplyExample;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ReplyExampleResource::class)]
class ReplyExampleResourceTest extends TestCase
{
    #[Test]
    public function it_lists_reply_examples(): void
    {
        $this->actingAs(User::factory()->create());
        $examples = ReplyExample::factory()->count(2)->create();

        Livewire::test(ManageReplyExamples::class)->assertCanSeeTableRecords($examples);
    }

    #[Test]
    public function it_creates_an_active_reply_example(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageReplyExamples::class)
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'Wedding, Malibu', 'reply' => 'Hi there! xo Jena'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(ReplyExample::class, ['title' => 'Wedding, Malibu', 'is_active' => true]);
    }

    #[Test]
    public function it_requires_a_reply(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageReplyExamples::class)
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'Wedding, Malibu', 'reply' => ''])
            ->assertHasActionErrors(['reply' => 'required']);
    }
}
