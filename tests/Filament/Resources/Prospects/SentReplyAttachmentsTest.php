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
    public function it_shows_the_saved_images_in_the_draft_ready_slide_over_between_the_reply_and_the_actions(): void
    {
        $prospect = Prospect::factory()->draftReady()->withReplyAttachments()->create(['generated_reply' => 'Draft body text', 'final_reply' => null]);

        $html = $this->reviewHtml($prospect);

        $this->assertSame(1, substr_count($html, 'data-test="sent-reply-attachments"'));
        $this->assertStringContainsString('custom-quote.png', $html);
        $reply = strpos($html, 'Draft body text');
        $images = strpos($html, 'data-test="sent-reply-attachments"');
        $actions = strpos($html, 'Approve &amp; Send');
        $this->assertTrue($reply < $images && $images < $actions);
    }

    #[Test]
    public function it_shows_the_saved_images_in_the_edited_reply_variant_once(): void
    {
        $prospect = Prospect::factory()->draftReady()->withReplyAttachments()->create([
            'generated_reply' => 'Draft body text',
            'final_reply' => 'Edited body text',
        ]);

        $html = $this->reviewHtml($prospect);

        $this->assertSame(1, substr_count($html, 'data-test="sent-reply-attachments"'));
        $this->assertTrue(strpos($html, 'Edited body text') < strpos($html, 'data-test="sent-reply-attachments"'));
    }

    #[Test]
    public function it_shows_no_images_in_the_draft_ready_slide_over_when_there_are_none(): void
    {
        $prospect = Prospect::factory()->draftReady()->create(['reply_attachments' => null]);

        $this->assertStringNotContainsString('data-test="sent-reply-attachments"', $this->reviewHtml($prospect));
    }

    #[Test]
    public function it_renders_each_attachment_as_a_thumbnail_with_a_one_line_name_label(): void
    {
        $prospect = Prospect::factory()->draftReady()->create([
            'status' => ProspectStatus::Replied,
            'replied_at' => now(),
            'reply_attachments' => [
                ['disk' => 'public', 'path' => 'gallery/stilt-walker.jpg', 'name' => 'stilt-walker.jpg'],
                ['disk' => 'local', 'path' => 'prospect-replies/1/custom-quote.png', 'name' => 'custom-quote.png'],
            ],
        ]);

        $html = $this->reviewHtml($prospect);

        $this->assertSame(2, substr_count($html, 'class="sra-thumb"'));
        $this->assertMatchesRegularExpression('/<div class="sra-thumb-label" title="stilt-walker\.jpg">stilt-walker\.jpg<\/div>/', $html);
        $this->assertMatchesRegularExpression('/<div class="sra-thumb-label" title="custom-quote\.png">custom-quote\.png<\/div>/', $html);
    }

    private function reviewHtml(Prospect $prospect): string
    {
        $component = Livewire::test(ManageProspects::class)
            ->mountAction(TestAction::make('review')->table($prospect));

        return (fn (): string => $this->getMountedActionSchema()->toHtml())->call($component->instance());
    }
}
