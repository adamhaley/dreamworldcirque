<?php

namespace Tests\Mail;

use App\Mail\ProspectReplyMail;
use App\Models\GalleryPhoto;
use App\Models\Prospect;
use Closure;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectReplyMail::class)]
class ProspectReplyMailTest extends TestCase
{
    #[Test]
    public function it_routes_customer_replies_to_jenas_forwarded_address(): void
    {
        $mail = new ProspectReplyMail(Prospect::factory()->draftReady()->make());

        $mail->assertHasReplyTo('jena@dreamworldcirque.com');
    }

    #[Test]
    public function it_bccs_the_monitored_inbox_so_jena_keeps_a_copy(): void
    {
        $mail = new ProspectReplyMail(Prospect::factory()->draftReady()->make());

        $mail->assertHasBcc('info@dreamworldcirque.com');
    }

    #[Test]
    public function it_has_the_expected_subject(): void
    {
        $mail = new ProspectReplyMail(Prospect::factory()->draftReady()->make());

        $mail->assertHasSubject('Re: Your inquiry to Dream World Cirque');
    }

    #[Test]
    public function it_sends_the_reply_text_verbatim_without_html_escaping(): void
    {
        $mail = new ProspectReplyMail(Prospect::factory()->make([
            'final_reply' => "We'd love to help -- call between 10 am & 4 pm, \"anytime\" <really>.",
        ]));

        $this->assertSame(
            "We'd love to help -- call between 10 am & 4 pm, \"anytime\" <really>.",
            trim($mail->render()),
        );
    }

    #[Test]
    public function it_attaches_the_gallery_photo_and_the_upload(): void
    {
        $mail = new ProspectReplyMail($this->prospectWithAttachments());

        $this->assertSame(['gallery-shot.jpg', 'upload.jpg'], array_keys($this->attachedFiles($mail)));
    }

    #[Test]
    public function it_has_no_attachments_when_the_prospect_has_none(): void
    {
        $withNull = new ProspectReplyMail(Prospect::factory()->draftReady()->make(['reply_attachments' => null]));
        $withEmpty = new ProspectReplyMail(Prospect::factory()->draftReady()->make(['reply_attachments' => []]));

        $this->assertSame([], $withNull->attachments());
        $this->assertSame([], $withEmpty->attachments());
    }

    #[Test]
    public function it_attaches_resized_jpeg_copies_instead_of_the_originals(): void
    {
        $mail = new ProspectReplyMail($this->prospectWithAttachments());

        $sizes = array_map(
            fn (string $contents): array => getimagesizefromstring($contents),
            $this->attachedFiles($mail),
        );

        $this->assertSame('image/jpeg', $sizes['upload.jpg']['mime']);
        $this->assertSame(1600, $sizes['upload.jpg'][0]);
        $this->assertSame(800, $sizes['upload.jpg'][1]);
        $this->assertSame('image/jpeg', $sizes['gallery-shot.jpg']['mime']);
        $this->assertSame(100, $sizes['gallery-shot.jpg'][0]);
    }

    /**
     * @return array<string, string>
     */
    private function attachedFiles(ProspectReplyMail $mail): array
    {
        $files = [];

        foreach ($mail->attachments() as $attachment) {
            $attachment->attachWith(
                fn (): null => null,
                function (Closure $data, Attachment $attachment) use (&$files): void {
                    $files[$attachment->as] = $data();
                },
            );
        }

        return $files;
    }

    private function prospectWithAttachments(): Prospect
    {
        Storage::fake('local');
        Storage::fake('public');

        $photo = GalleryPhoto::factory()->create(['image' => 'gallery/gallery-shot.png']);
        Storage::disk('public')->put($photo->image, $this->pngOf(100, 50));
        Storage::disk('local')->put('reply-attachments/upload.png', $this->pngOf(3200, 1600));

        return Prospect::factory()->draftReady()->make([
            'reply_attachments' => [
                ['disk' => 'public', 'path' => $photo->image, 'name' => 'gallery-shot.png'],
                ['disk' => 'local', 'path' => 'reply-attachments/upload.png', 'name' => 'upload.png'],
            ],
        ]);
    }

    private function pngOf(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
