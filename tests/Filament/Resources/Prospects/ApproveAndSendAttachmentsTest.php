<?php

namespace Tests\Filament\Resources\Prospects;

use App\Enums\ProspectStatus;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Filament\Resources\Prospects\ProspectResource;
use App\Mail\ProspectReplyMail;
use App\Models\GalleryPhoto;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Prospects\ProspectReplyAttachmentService;
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
    public function it_sends_one_mail_with_resized_jpeg_copies_of_the_saved_gallery_photos_and_upload(): void
    {
        $photos = collect([[2400, 1200], [400, 300]])->map(fn (array $size): GalleryPhoto => $this->galleryPhotoWithImage(...$size));
        $upload = UploadedFile::fake()->image('stage.png', 3000, 2000);
        Storage::disk('local')->put('prospect-replies/1/stage.png', $upload->getContent());
        $prospect = Prospect::factory()->draftReady()->create([
            'email' => 'lead@example.com',
            'reply_attachments' => [
                ...$photos->map(fn (GalleryPhoto $photo): array => ['disk' => 'public', 'path' => $photo->image, 'name' => basename($photo->image)])->all(),
                ['disk' => 'local', 'path' => 'prospect-replies/1/stage.png', 'name' => 'stage.png'],
            ],
        ]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->approveAction($prospect))
            ->assertHasNoActionErrors();

        $this->assertSame(ProspectStatus::Replied, $prospect->refresh()->status);
        $this->assertCount(3, $prospect->reply_attachments);
        Mail::assertSent(ProspectReplyMail::class, 1);
        Mail::assertSent(ProspectReplyMail::class, function (ProspectReplyMail $mail): bool {
            $sizes = array_map(fn (string $data): array => getimagesizefromstring($data), $this->attachedFiles($mail));

            $mail->assertHasBcc('info@dreamworldcirque.com');

            return $mail->hasTo('lead@example.com')
                && count($sizes) === 3
                && collect($sizes)->every(fn (array $size): bool => $size['mime'] === 'image/jpeg' && max($size[0], $size[1]) <= ProspectReplyAttachmentService::MAX_LONGEST_SIDE);
        });
    }

    #[Test]
    public function it_sends_one_mail_with_no_attachments_when_none_are_saved(): void
    {
        $prospect = Prospect::factory()->draftReady()->create(['reply_attachments' => null]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->approveAction($prospect))
            ->assertHasNoActionErrors();

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
