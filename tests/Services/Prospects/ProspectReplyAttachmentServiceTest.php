<?php

namespace Tests\Services\Prospects;

use App\Services\Prospects\ProspectReplyAttachmentData;
use App\Services\Prospects\ProspectReplyAttachmentService;
use App\Services\Prospects\ProspectReplyAttachmentsRejectedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectReplyAttachmentService::class)]
class ProspectReplyAttachmentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function store(string $disk, string $name, int $width, int $height): ProspectReplyAttachmentData
    {
        $path = Storage::disk($disk)->putFileAs('images', UploadedFile::fake()->image($name, $width, $height), $name);

        return new ProspectReplyAttachmentData($disk, $path, $name);
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $binary): array
    {
        $info = getimagesizefromstring($binary);

        return [$info[0], $info[1]];
    }

    #[Test]
    public function it_scales_a_large_png_down_to_a_jpeg_copy(): void
    {
        $attachment = $this->store('public', 'big.png', 3000, 2000);

        $result = (new ProspectReplyAttachmentService)->prepare([$attachment]);

        $this->assertSame(
            [ProspectReplyAttachmentService::MAX_LONGEST_SIDE, (int) round(ProspectReplyAttachmentService::MAX_LONGEST_SIDE * 2 / 3)],
            $this->dimensions($result[0]->contents),
        );
    }

    #[Test]
    public function it_returns_jpeg_metadata_with_a_jpg_filename(): void
    {
        $attachment = $this->store('public', 'big.png', 300, 200);

        $result = (new ProspectReplyAttachmentService)->prepare([$attachment]);

        $this->assertSame(['big.jpg', 'image/jpeg', IMAGETYPE_JPEG], [
            $result[0]->filename,
            $result[0]->mime,
            getimagesizefromstring($result[0]->contents)[2],
        ]);
    }

    #[Test]
    public function it_leaves_the_original_file_unchanged(): void
    {
        $attachment = $this->store('public', 'big.png', 3000, 2000);
        $before = Storage::disk('public')->get($attachment->path);

        (new ProspectReplyAttachmentService)->prepare([$attachment]);

        $this->assertSame($before, Storage::disk('public')->get($attachment->path));
    }

    #[Test]
    public function it_does_not_upscale_small_images(): void
    {
        $attachment = $this->store('public', 'small.png', 400, 300);

        $result = (new ProspectReplyAttachmentService)->prepare([$attachment]);

        $this->assertSame([400, 300], $this->dimensions($result[0]->contents));
    }

    #[Test]
    public function it_prepares_files_from_both_public_and_local_disks(): void
    {
        $attachments = [
            $this->store('public', 'gallery.png', 800, 600),
            $this->store('local', 'upload.jpg', 800, 600),
        ];

        $result = (new ProspectReplyAttachmentService)->prepare($attachments);

        $this->assertSame(['gallery.jpg', 'upload.jpg'], array_map(fn ($a) => $a->filename, $result));
    }

    #[Test]
    public function it_rejects_more_than_ten_attachments(): void
    {
        $attachment = $this->store('public', 'a.png', 50, 50);

        $this->expectException(ProspectReplyAttachmentsRejectedException::class);

        (new ProspectReplyAttachmentService)->prepare(array_fill(0, 11, $attachment));
    }

    #[Test]
    public function it_rejects_copies_over_the_total_size_cap(): void
    {
        $attachment = $this->store('public', 'a.png', 800, 600);

        $this->expectException(ProspectReplyAttachmentsRejectedException::class);

        (new ProspectReplyAttachmentService(maxTotalBytes: 10))->prepare([$attachment]);
    }

    #[Test]
    public function it_defaults_the_total_size_cap_to_eighteen_megabytes(): void
    {
        $this->assertSame(18 * 1024 * 1024, ProspectReplyAttachmentService::MAX_TOTAL_BYTES);
    }

    #[Test]
    public function it_returns_an_empty_list_for_no_attachments(): void
    {
        $this->assertSame([], (new ProspectReplyAttachmentService)->prepare([]));
    }
}
