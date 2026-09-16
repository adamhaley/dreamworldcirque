<?php

namespace Tests\Filament\Resources\Pages;

use App\Enums\PageTemplate;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PageResource::class)]
class PageResourceTest extends TestCase
{
    #[Test]
    public function it_lists_pages(): void
    {
        $this->actingAs(User::factory()->create());
        $pages = Page::factory()->count(3)->create();

        Livewire::test(ListPages::class)->assertCanSeeTableRecords($pages);
    }

    #[Test]
    public function it_lets_an_editor_update_page_content(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->template(PageTemplate::Service)->create();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['hero_heading' => 'Aerial magic', 'card_excerpt' => 'Silks, hoop and trapeze.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Aerial magic', $page->fresh()->hero_heading);
    }

    #[Test]
    public function it_locks_system_page_structure_for_editors(): void
    {
        $this->actingAs(User::factory()->create());
        $gallery = Page::factory()->template(PageTemplate::Gallery)->create();

        Livewire::test(EditPage::class, ['record' => $gallery->getRouteKey()])
            ->assertFormFieldDisabled('template')
            ->assertFormFieldDisabled('slug');
    }

    #[Test]
    public function it_hides_the_delete_action_on_system_pages_for_editors(): void
    {
        $this->actingAs(User::factory()->create());
        $gallery = Page::factory()->template(PageTemplate::Gallery)->create();

        Livewire::test(EditPage::class, ['record' => $gallery->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    #[Test]
    public function it_creates_a_child_page_under_its_parent(): void
    {
        $this->actingAs(User::factory()->create());
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Fire Performers', 'slug' => 'fire-performers', 'parent_id' => $services->id, 'template' => PageTemplate::Service->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Page::class, ['path' => 'services/fire-performers']);
    }

    #[Test]
    public function it_rejects_a_duplicate_slug_under_the_same_parent(): void
    {
        $this->actingAs(User::factory()->create());
        $services = Page::factory()->create(['slug' => 'services']);
        Page::factory()->create(['slug' => 'fire', 'parent_id' => $services->id]);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Fire', 'slug' => 'fire', 'parent_id' => $services->id, 'template' => PageTemplate::Service->value])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    #[Test]
    public function it_only_offers_editors_non_system_templates(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Sneaky', 'slug' => 'sneaky', 'template' => PageTemplate::Home->value])
            ->call('create')
            ->assertHasFormErrors(['template']);
    }
}
