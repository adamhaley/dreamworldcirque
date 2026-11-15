<?php

declare(strict_types=1);

namespace App\Services\Prospects;

final readonly class PreparedReplyAttachment
{
    public function __construct(
        public string $filename,
        public string $contents,
        public string $mime = 'image/jpeg',
    ) {}
}
