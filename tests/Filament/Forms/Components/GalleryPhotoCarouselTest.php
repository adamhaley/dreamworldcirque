<?php

namespace Tests\Filament\Forms\Components;

use App\Filament\Forms\Components\GalleryPhotoCarousel;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Models\GalleryPhoto;
use App\Models\Page;
use App\Models\Prospect;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GalleryPhotoCarousel::class)]
class GalleryPhotoCarouselTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    public static function replyActionProvider(): Generator
    {
        yield 'edit reply' => ['editDraftReply'];
    }

    /**
     * @return list<TestAction>
     */
    private function replyAction(string $name, Prospect $prospect): array
    {
        return [
            TestAction::make('review')->table($prospect),
            TestAction::make($name)->schemaComponent(true, 'mountedActionSchema0'),
        ];
    }

    /**
     * Filament renders mounted action modals as Livewire partials rather than in the page HTML.
     */
    private function renderedModals(Testable $testable): string
    {
        return implode('', $testable->effects['partials'] ?? []);
    }

    #[Test]
    #[DataProvider('replyActionProvider')]
    public function it_renders_each_gallery_photo_with_its_alt_text_as_title_and_alt_without_a_caption(string $action): void
    {
        $prospect = Prospect::factory()->draftReady()->create();
        $photo = GalleryPhoto::factory()->create(['alt' => 'Aerial silks over the stage']);

        $html = $this->renderedModals(
            Livewire::test(ManageProspects::class)->mountAction($this->replyAction($action, $prospect)),
        );

        $this->assertStringContainsString('data-photo-path="'.e($photo->image).'"', $html);
        $this->assertStringContainsString('src="'.e(Storage::disk('public')->url($photo->image)).'"', $html);
        $this->assertStringContainsString('title="Aerial silks over the stage"', $html);
        $this->assertStringContainsString('alt="Aerial silks over the stage"', $html);
        $this->assertStringNotContainsString('gpc-caption', $html);
    }

    #[Test]
    public function it_falls_back_to_the_title_when_a_photo_has_no_alt_text(): void
    {
        $photo = GalleryPhoto::factory()->create(['alt' => '', 'title' => 'Fire dancers']);

        $photos = GalleryPhotoCarousel::make('website_images')->getPhotos();

        $this->assertSame(
            ['path' => $photo->image, 'url' => Storage::disk('public')->url($photo->image), 'alt' => '', 'label' => 'Fire dancers'],
            $photos[0],
        );
    }

    #[Test]
    public function it_lists_gallery_photos_in_gallery_order(): void
    {
        $second = GalleryPhoto::factory()->create(['sort_order' => 2]);
        $first = GalleryPhoto::factory()->create(['sort_order' => 1]);

        $paths = array_column(GalleryPhotoCarousel::make('website_images')->getPhotos(), 'path');

        $this->assertSame([$first->image, $second->image], $paths);
    }

    #[Test]
    public function it_preselects_the_photos_already_saved_on_the_reply(): void
    {
        $photo = GalleryPhoto::factory()->create();
        GalleryPhoto::factory()->create();
        $prospect = Prospect::factory()->draftReady()->create([
            'reply_attachments' => [['disk' => 'public', 'path' => $photo->image, 'name' => basename($photo->image)]],
        ]);

        Livewire::test(ManageProspects::class)
            ->mountAction($this->replyAction('editDraftReply', $prospect))
            ->assertSchemaStateSet(['website_images' => [$photo->image]]);
    }

    #[Test]
    public function it_dehydrates_its_state_to_a_unique_list_of_offered_paths(): void
    {
        $prospect = Prospect::factory()->draftReady()->create();
        $photos = GalleryPhoto::factory()->count(2)->create();

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('editDraftReply', $prospect), [
                'final_reply' => 'Edited reply',
                'website_images' => [$photos[0]->image, $photos[1]->image, $photos[0]->image, '', null, 'gallery/not-offered.jpg'],
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(
            $photos->pluck('image')->all(),
            array_column($prospect->refresh()->reply_attachments, 'path'),
        );
    }

    #[Test]
    public function it_renders_the_staged_area_above_the_carousel(): void
    {
        $prospect = Prospect::factory()->draftReady()->create();
        GalleryPhoto::factory()->create();

        $html = $this->renderedModals(
            Livewire::test(ManageProspects::class)->mountAction($this->replyAction('editDraftReply', $prospect)),
        );

        $stagedPosition = strpos($html, 'class="gpc-staged');
        $viewportPosition = strpos($html, 'class="gpc-viewport');

        $this->assertNotFalse($stagedPosition);
        $this->assertNotFalse($viewportPosition);
        $this->assertLessThan($viewportPosition, $stagedPosition);
    }

    #[Test]
    public function it_shows_uploaded_images_in_the_staged_area(): void
    {
        $prospect = Prospect::factory()->draftReady()->create();

        $html = $this->renderedModals(
            Livewire::test(ManageProspects::class)
                ->mountAction($this->replyAction('editDraftReply', $prospect))
                ->fillForm(['uploads' => [UploadedFile::fake()->image('backstage.png', 200, 200)]]),
        );

        $this->assertStringContainsString('data-staged-upload', $html);
        $this->assertStringContainsString('backstage.png', $html);
    }

    #[Test]
    public function it_offers_each_pages_hero_circle_and_card_images_after_the_gallery_photos(): void
    {
        $photo = GalleryPhoto::factory()->create();
        Page::factory()->create([
            'title' => 'About',
            'hero_image' => 'pages/about-hero.jpg',
            'circle_image' => 'pages/about-circle.jpg',
            'circle_image_alt' => 'Juggler on a unicycle',
            'card_image' => 'pages/about-card.jpg',
        ]);

        $photos = GalleryPhotoCarousel::make('website_images')->getPhotos();

        $this->assertSame($photo->image, $photos[0]['path']);
        $this->assertSame(
            [
                ['path' => 'pages/about-hero.jpg', 'url' => Storage::disk('public')->url('pages/about-hero.jpg'), 'alt' => '', 'label' => 'About, hero'],
                ['path' => 'pages/about-circle.jpg', 'url' => Storage::disk('public')->url('pages/about-circle.jpg'), 'alt' => 'Juggler on a unicycle', 'label' => 'About, circle'],
                ['path' => 'pages/about-card.jpg', 'url' => Storage::disk('public')->url('pages/about-card.jpg'), 'alt' => '', 'label' => 'About, card'],
            ],
            array_slice($photos, 1),
        );
    }

    #[Test]
    public function it_never_offers_a_pages_og_image(): void
    {
        Page::factory()->create(['hero_image' => 'pages/hero.jpg', 'og_image' => 'pages/og.jpg']);

        $paths = GalleryPhotoCarousel::make('website_images')->getPhotoPaths();

        $this->assertSame(['pages/hero.jpg'], $paths);
    }

    public static function duplicateImageProvider(): Generator
    {
        yield 'card image is the same file as the hero' => [null, ['title' => 'About', 'hero_image' => 'pages/shared.jpg', 'card_image' => 'pages/shared.jpg'], 'About, hero'];
        yield 'gallery photo is also a page image' => ['pages/shared.jpg', ['title' => 'About', 'hero_image' => 'pages/shared.jpg'], 'Gallery shot'];
    }

    /**
     * @param  array<string, string>  $pageAttributes
     */
    #[Test]
    #[DataProvider('duplicateImageProvider')]
    public function it_offers_a_file_used_twice_only_once_at_its_first_occurrence(?string $galleryImage, array $pageAttributes, string $expectedLabel): void
    {
        if ($galleryImage !== null) {
            GalleryPhoto::factory()->create(['image' => $galleryImage, 'alt' => 'Gallery shot']);
        }
        Page::factory()->create($pageAttributes);

        $photos = GalleryPhotoCarousel::make('website_images')->getPhotos();

        $this->assertSame([['pages/shared.jpg', $expectedLabel]], array_map(
            fn (array $item): array => [$item['path'], $item['label']],
            $photos,
        ));
    }
}
