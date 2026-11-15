<?php

declare(strict_types=1);

namespace App\Services\Prospects;

final readonly class ProspectReplyAttachmentData
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $originalName,
    ) {}

    /**
     * @param  array{disk: string, path: string, name: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            disk: (string) $data['disk'],
            path: (string) $data['path'],
            originalName: (string) $data['name'],
        );
    }

    /**
     * @return array{disk: string, path: string, name: string}
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $this->originalName,
        ];
    }
}
