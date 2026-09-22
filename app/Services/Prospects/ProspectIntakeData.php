<?php

declare(strict_types=1);

namespace App\Services\Prospects;

readonly class ProspectIntakeData
{
    /**
     * @param  list<string>|null  $interests
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $message = null,
        public ?array $interests = null,
        public ?array $rawPayload = null,
    ) {}
}
