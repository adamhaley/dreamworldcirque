<?php

namespace Tests\Filament\Resources;

use App\Filament\Resources\FaqItems\FaqItemResource;
use App\Filament\Resources\FaqItems\Pages\ManageFaqItems;
use App\Filament\Resources\GalleryPhotos\GalleryPhotoResource;
use App\Filament\Resources\GalleryPhotos\Pages\ManageGalleryPhotos;
use App\Filament\Resources\Quotes\Pages\ManageQuotes;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Spotlights\Pages\ManageSpotlights;
use App\Filament\Resources\Spotlights\SpotlightResource;
use App\Models\FaqItem;
use App\Models\GalleryPhoto;
use App\Models\Quote;
use App\Models\Spotlight;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Generator;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(QuoteResource::class)]
#[CoversClass(FaqItemResource::class)]
#[CoversClass(GalleryPhotoResource::class)]
#[CoversClass(SpotlightResource::class)]
class ContentResourcesTest extends TestCase
{
    public static function resourceProvider(): Generator
    {
        yield 'quotes' => [ManageQuotes::class, Quote::class];
        yield 'faq items' => [ManageFaqItems::class, FaqItem::class];
        yield 'gallery photos' => [ManageGalleryPhotos::class, GalleryPhoto::class];
        yield 'spotlights' => [ManageSpotlights::class, Spotlight::class];
    }

    /**
     * @param  class-string  $page
     * @param  class-string<Model>  $model
     */
    #[Test]
    #[DataProvider('resourceProvider')]
    public function it_lists_records_for_editors(string $page, string $model): void
    {
        $this->actingAs(User::factory()->create());
        $records = $model::factory()->count(2)->create();

        Livewire::test($page)->assertCanSeeTableRecords($records);
    }

    #[Test]
    public function it_creates_a_quote(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageQuotes::class)
            ->callAction(TestAction::make('create')->table(), data: ['body' => 'Simply spellbinding', 'name' => 'Jordan Lee', 'affiliation' => 'Gala Co'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(Quote::class, ['body' => 'Simply spellbinding', 'is_active' => true]);
    }

    #[Test]
    public function it_updates_spotlight_bullets_and_buttons(): void
    {
        $this->actingAs(User::factory()->create());
        $spotlight = Spotlight::factory()->create();

        Livewire::test(ManageSpotlights::class)
            ->callTableAction(EditAction::class, $spotlight, data: [
                'bullets' => [['bullet' => 'First point'], ['bullet' => 'Second point']],
                'buttons' => [['label' => 'Get a Quote', 'url' => '#contact', 'style' => 'primary']],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(['First point', 'Second point'], $spotlight->fresh()->bullets);
    }

    #[Test]
    public function it_reorders_gallery_photos(): void
    {
        $this->actingAs(User::factory()->create());
        [$first, $second] = GalleryPhoto::factory()->count(2)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();

        Livewire::test(ManageGalleryPhotos::class)->call('reorderTable', [$second->getKey(), $first->getKey()]);

        $this->assertLessThan($first->fresh()->sort_order, $second->fresh()->sort_order);
    }
}
