<?php

namespace Tests\Filament\Resources\Prospects;

use App\Enums\ProspectStatus;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Filament\Resources\Prospects\ProspectResource;
use App\Models\GalleryPhoto;
use App\Models\Prospect;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectResource::class)]
class SentReplyAttachmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
    }

    #[Test]
    public function it_lists_sent_gallery_photos_and_uploads_with_names_and_urls(): void
    {
        $photo = GalleryPhoto::factory()->create(['image' => 'gallery/stilt-walker.jpg']);
        $prospect = Prospect::factory()->draftReady()->create([
            'status' => ProspectStatus::Replied,
            'replied_at' => now(),
            'reply_attachments' => [
                ['disk' => 'public', 'path' => $photo->image, 'name' => 'stilt-walker.jpg'],
                ['disk' => 'local', 'path' => 'prospect-replies/1/custom-quote.png', 'name' => 'custom-quote.png'],
            ],
        ]);

        $html = $this->reviewHtml($prospect);

        $this->assertStringContainsString('stilt-walker.jpg', $html);
        $this->assertStringContainsString('custom-quote.png', $html);
        $this->assertStringContainsString(e(Storage::disk('public')->url('gallery/stilt-walker.jpg')), $html);
        $this->assertStringContainsString(e(route('admin.prospect-reply-attachments.show', [$prospect, 1])), $html);
        $this->assertStringNotContainsString('prospect-replies/1/custom-quote.png', $html);
    }

    #[Test]
    public function it_shows_no_attachments_list_for_a_sent_reply_without_attachments(): void
    {
        $prospect = Prospect::factory()->draftReady()->create([
            'status' => ProspectStatus::Replied,
            'replied_at' => now(),
            'reply_attachments' => null,
        ]);

        $this->assertStringNotContainsString('data-test="sent-reply-attachments"', $this->reviewHtml($prospect));
    }

    #[Test]
    public function it_hides_the_sent_attachments_list_for_an_unsent_draft(): void
    {
        $prospect = Prospect::factory()->draftReady()->withReplyAttachments()->create();

        $html = $this->reviewHtml($prospect);

        $this->assertStringNotContainsString('data-test="sent-reply-attachments"', $html);
        $this->assertStringNotContainsString('custom-quote.png', $html);
    }

    private function reviewHtml(Prospect $prospect): string
    {
        $component = Livewire::test(ManageProspects::class)
            ->mountAction(TestAction::make('review')->table($prospect));

        return (fn (): string => $this->getMountedActionSchema()->toHtml())->call($component->instance());
    }
}
