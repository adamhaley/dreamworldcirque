<?php

declare(strict_types=1);

namespace App\Services\Prospects;

use RuntimeException;

final class ProspectReplyAttachmentsRejectedException extends RuntimeException
{
    public static function tooMany(int $count, int $max): self
    {
        return new self("You attached {$count} images, but a reply can include at most {$max}. Please remove some and try again.");
    }

    public static function tooLarge(int $bytes, int $maxBytes): self
    {
        $size = number_format($bytes / 1024 / 1024, 1);
        $max = number_format($maxBytes / 1024 / 1024, 1);

        return new self("The attached images total {$size} MB after resizing, which is over the {$max} MB limit for an email. Please remove some images and try again.");
    }

    public static function unreadable(string $name): self
    {
        return new self("The image \"{$name}\" could not be read. Please remove it or replace it with a JPEG or PNG and try again.");
    }
}
