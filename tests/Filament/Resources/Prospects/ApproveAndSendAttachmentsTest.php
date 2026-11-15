<?php

namespace Tests\Filament\Resources\Prospects;

use App\Enums\ProspectStatus;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Filament\Resources\Prospects\ProspectResource;
use App\Mail\ProspectReplyMail;
use App\Models\GalleryPhoto;
use App\Models\Prospect;
use App\Models\User;
use Closure;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectResource::class)]
class ApproveAndSendAttachmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Mail::fake();
        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
    }

    #[Test]
    public function it_sends_one_mail_with_resized_jpeg_copies_of_gallery_photos_and_the_upload(): void
    {
        $prospect = Prospect::factory()->draftReady()->create(['email' => 'lead@example.com']);
        $photos = collect([[2400, 1200], [400, 300]])->map(fn (array $size): GalleryPhoto => $this->galleryPhotoWithImage(...$size));

        Livewire::test(ManageProspects::class)
            ->callAction($this->approveAction($prospect), [
                'final_reply' => 'Final reply',
                'gallery_photo_ids' => $photos->pluck('id')->all(),
                'uploads' => [UploadedFile::fake()->image('stage.png', 3000, 2000)],
            ])
            ->assertHasNoFormErrors();

        Mail::assertSent(ProspectReplyMail::class, 1);
        Mail::assertSent(ProspectReplyMail::class, function (ProspectReplyMail $mail): bool {
            $sizes = array_map(fn (string $data): array => getimagesizefromstring($data), $this->attachedFiles($mail));

            $mail->assertHasBcc('info@dreamworldcirque.com');

            return $mail->hasTo('lead@example.com')
                && count($sizes) === 3
                && collect($sizes)->every(fn (array $size): bool => $size['mime'] === 'image/jpeg' && max($size[0], $size[1]) <= 1600);
        });
    }

    #[Test]
    public function it_stores_the_upload_privately_and_records_all_three_attachments_on_the_prospect(): void
    {
        $prospect = Prospect::factory()->draftReady()->create();
        $photos = collect([[2400, 1200], [400, 300]])->map(fn (array $size): GalleryPhoto => $this->galleryPhotoWithImage(...$size));

        Livewire::test(ManageProspects::class)
            ->callAction($this->approveAction($prospect), [
                'final_reply' => 'Final reply',
                'gallery_photo_ids' => $photos->pluck('id')->all(),
                'uploads' => [UploadedFile::fake()->image('stage.png', 3000, 2000)],
            ]);

        $attachments = collect($prospect->refresh()->reply_attachments);
        $upload = $attachments->firstWhere('disk', 'local');
        $this->assertSame(ProspectStatus::Replied, $prospect->status);
        $this->assertCount(3, $attachments);
        $this->assertSame($photos->pluck('image')->all(), $attachments->where('disk', 'public')->pluck('path')->all());
        $this->assertStringStartsWith("prospect-replies/{$prospect->id}/", $upload['path']);
        Storage::disk('local')->assertExists($upload['path']);
    }

    #[Test]
    public function it_sends_one_mail_with_no_attachments_when_none_are_chosen(): void
    {
        $prospect = Prospect::factory()->draftReady()->create();

        Livewire::test(ManageProspects::class)
            ->callAction($this->approveAction($prospect), ['final_reply' => 'Final reply'])
            ->assertHasNoFormErrors();

        Mail::assertSent(ProspectReplyMail::class, 1);
        Mail::assertSent(ProspectReplyMail::class, fn (ProspectReplyMail $mail): bool => $mail->attachments() === []);
    }

    /**
     * @return list<TestAction>
     */
    private function approveAction(Prospect $prospect): array
    {
        return [
            TestAction::make('review')->table($prospect),
            TestAction::make('approveDraftAndSend')->schemaComponent(true, 'mountedActionSchema0'),
        ];
    }

    private function galleryPhotoWithImage(int $width, int $height): GalleryPhoto
    {
        $photo = GalleryPhoto::factory()->create();
        Storage::disk('public')->put($photo->image, UploadedFile::fake()->image('photo.jpg', $width, $height)->getContent());

        return $photo;
    }

    /**
     * @return list<string>
     */
    private function attachedFiles(ProspectReplyMail $mail): array
    {
        $files = [];

        foreach ($mail->attachments() as $attachment) {
            $attachment->attachWith(
                fn (): null => null,
                function (Closure $data, Attachment $attachment) use (&$files): void {
                    $files[] = $data();
                },
            );
        }

        return $files;
    }
}
