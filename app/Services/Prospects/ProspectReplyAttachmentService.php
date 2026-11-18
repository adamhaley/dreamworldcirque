<?php

declare(strict_types=1);

namespace App\Services\Prospects;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class ProspectReplyAttachmentService
{
    public const int MAX_ATTACHMENTS = 10;

    public const int MAX_LONGEST_SIDE = 2560;

    public const int JPEG_QUALITY = 90;

    public const int MAX_TOTAL_BYTES = 18 * 1024 * 1024;

    public function __construct(private readonly int $maxTotalBytes = self::MAX_TOTAL_BYTES) {}

    /**
     * Build resized JPEG copies of the given images; originals are never touched.
     *
     * @param  list<ProspectReplyAttachmentData>  $attachments
     * @return list<PreparedReplyAttachment>
     *
     * @throws ProspectReplyAttachmentsRejectedException
     */
    public function prepare(array $attachments): array
    {
        if (count($attachments) > self::MAX_ATTACHMENTS) {
            throw ProspectReplyAttachmentsRejectedException::tooMany(count($attachments), self::MAX_ATTACHMENTS);
        }

        $manager = new ImageManager(new Driver);
        $prepared = [];
        $total = 0;

        foreach ($attachments as $attachment) {
            try {
                $original = Storage::disk($attachment->disk)->get($attachment->path);

                if ($original === null) {
                    throw new \RuntimeException('Missing file');
                }

                $contents = (string) $manager->decodeBinary($original)
                    ->scaleDown(self::MAX_LONGEST_SIDE, self::MAX_LONGEST_SIDE)
                    ->encode(new JpegEncoder(quality: self::JPEG_QUALITY));
            } catch (Throwable) {
                throw ProspectReplyAttachmentsRejectedException::unreadable($attachment->originalName);
            }

            $total += strlen($contents);

            $prepared[] = new PreparedReplyAttachment(
                filename: pathinfo($attachment->originalName, PATHINFO_FILENAME).'.jpg',
                contents: $contents,
                mime: 'image/jpeg',
            );
        }

        if ($total > $this->maxTotalBytes) {
            throw ProspectReplyAttachmentsRejectedException::tooLarge($total, $this->maxTotalBytes);
        }

        return $prepared;
    }
}
