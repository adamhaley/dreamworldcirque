<?php

namespace App\Filament\Forms\Components;

use App\Models\GalleryPhoto;
use App\Models\Page;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Swipeable carousel of the website's images: Gallery photos (in gallery order), then each Page's
 * hero, circle and card images. Each image is keyed by its public-disk path and offered once.
 *
 * The field's state is the staged attachment order: selected public-disk paths in the order they
 * were picked, interleaved with upload tokens (UPLOAD_TOKEN_PREFIX + the sibling FileUpload's file
 * key or stored path) in the order the files were uploaded. When dehydrated, each upload token is
 * rewritten to UPLOAD_TOKEN_PREFIX + the file's position in the FileUpload's dehydrated list.
 */
class GalleryPhotoCarousel extends Field
{
    public const UPLOAD_TOKEN_PREFIX = 'upload:';

    protected string $view = 'filament.forms.components.gallery-photo-carousel';

    protected string|Closure|null $stagedUploadsStatePath = null;

    protected string|Closure $uploadsDisk = 'local';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(static function (GalleryPhotoCarousel $component, mixed $state): void {
            $component->state($component->normalizePaths($state));
        });

        $this->dehydrateStateUsing(static fn (GalleryPhotoCarousel $component, mixed $state): array => $component->dehydrateOrder($state));

        $this->rule('array');
    }

    /**
     * Show the files of a sibling FileUpload field in the Staged area alongside the selected photos.
     */
    public function stagedUploadsFrom(string|Closure|null $statePath, string|Closure $disk = 'local'): static
    {
        $this->stagedUploadsStatePath = $statePath;
        $this->uploadsDisk = $disk;

        return $this;
    }

    /**
     * @return list<array{path: string, url: string, alt: string, label: string}>
     */
    public function getPhotos(): array
    {
        $photos = [];

        $add = function (?string $path, string $alt, string $label) use (&$photos): void {
            if (blank($path) || array_key_exists($path, $photos)) {
                return;
            }

            $photos[$path] = [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'alt' => $alt,
                'label' => $label,
            ];
        };

        GalleryPhoto::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(fn (GalleryPhoto $photo) => $add(
                $photo->image,
                (string) ($photo->alt ?? ''),
                (string) ($photo->alt ?: $photo->title ?: basename((string) $photo->image)),
            ));

        Page::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'title', 'hero_image', 'circle_image', 'circle_image_alt', 'card_image'])
            ->each(function (Page $page) use ($add): void {
                $add($page->hero_image, '', "{$page->title}, hero");
                $add($page->circle_image, (string) ($page->circle_image_alt ?? ''), "{$page->title}, circle");
                $add($page->card_image, '', "{$page->title}, card");
            });

        return array_values($photos);
    }

    /**
     * The public-disk paths of the offered images, in carousel order.
     *
     * @return list<string>
     */
    public function getPhotoPaths(): array
    {
        return array_column($this->getPhotos(), 'path');
    }

    /**
     * @return list<array{key: string, token: string, name: string, url: string|null}>
     */
    public function getStagedUploads(): array
    {
        $files = $this->getUploadFiles();
        $tokens = $this->normalizePaths($this->getRawState());
        $uploads = [];

        foreach ($files as $key => $file) {
            $key = (string) $key;
            $token = self::UPLOAD_TOKEN_PREFIX.$key;

            foreach ($tokens as $candidate) {
                if (self::uploadKeyForToken($candidate, $files) === $key) {
                    $token = $candidate;

                    break;
                }
            }

            if ($file instanceof TemporaryUploadedFile) {
                $uploads[] = [
                    'key' => $key,
                    'token' => $token,
                    'name' => $file->getClientOriginalName(),
                    'url' => $this->temporaryUploadUrl($file),
                ];
            } elseif (is_string($file) && $file !== '') {
                $uploads[] = [
                    'key' => $key,
                    'token' => $token,
                    'name' => basename($file),
                    'url' => $this->storedUploadUrl($file),
                ];
            }
        }

        return $uploads;
    }

    /**
     * Keep the staged order in step with the sibling FileUpload: drop tokens for removed files and
     * append a token for each newly uploaded file, so it lands after everything staged before it.
     *
     * @param  array<array-key, mixed>|null  $files  the FileUpload's raw state, keyed by file key
     * @return list<string>
     */
    public function syncUploadTokens(mixed $state, ?array $files): array
    {
        $files ??= [];
        $order = [];
        $referencedKeys = [];

        foreach ($this->normalizePaths($state) as $token) {
            if (! str_starts_with($token, self::UPLOAD_TOKEN_PREFIX)) {
                $order[] = $token;

                continue;
            }

            $key = self::uploadKeyForToken($token, $files);

            if ($key !== null && ! in_array($key, $referencedKeys, true)) {
                $order[] = $token;
                $referencedKeys[] = $key;
            }
        }

        foreach (array_keys($files) as $key) {
            if (! in_array((string) $key, $referencedKeys, true)) {
                $order[] = self::UPLOAD_TOKEN_PREFIX.$key;
            }
        }

        return $order;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getUploadFiles(): array
    {
        $statePath = $this->evaluate($this->stagedUploadsStatePath);

        if (blank($statePath)) {
            return [];
        }

        // Read the raw state: Get returns the FileUpload's cast state, which renumbers the files and
        // drops the keys that upload tokens refer to.
        $files = data_get($this->getLivewire(), $this->resolveRelativeStatePath($statePath));

        return is_array($files) ? $files : [];
    }

    /**
     * The FileUpload key an upload token refers to, matching either the file's key (a fresh upload)
     * or its stored path (a file saved on the reply earlier).
     *
     * @param  array<array-key, mixed>  $files
     */
    private static function uploadKeyForToken(string $token, array $files): ?string
    {
        if (! str_starts_with($token, self::UPLOAD_TOKEN_PREFIX)) {
            return null;
        }

        $reference = substr($token, strlen(self::UPLOAD_TOKEN_PREFIX));

        if (array_key_exists($reference, $files)) {
            return $reference;
        }

        $key = array_search($reference, $files, true);

        return $key === false ? null : (string) $key;
    }

    /**
     * Rewrite upload tokens to the files' positions in the FileUpload's dehydrated list, which drops
     * the keys but keeps their order.
     *
     * @return list<string>
     */
    private function dehydrateOrder(mixed $state): array
    {
        $files = $this->getUploadFiles();
        $positions = array_flip(array_map(strval(...), array_keys($files)));
        $order = [];

        foreach ($this->normalizePaths($state) as $token) {
            if (! str_starts_with($token, self::UPLOAD_TOKEN_PREFIX)) {
                $order[] = $token;

                continue;
            }

            $key = self::uploadKeyForToken($token, $files);

            if ($key !== null) {
                $order[] = self::UPLOAD_TOKEN_PREFIX.$positions[$key];
            }
        }

        return $order;
    }

    private function temporaryUploadUrl(TemporaryUploadedFile $file): ?string
    {
        try {
            return $file->isPreviewable() ? $file->temporaryUrl() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function storedUploadUrl(string $path): ?string
    {
        try {
            return Storage::disk($this->evaluate($this->uploadsDisk))->temporaryUrl($path, now()->addMinutes(30));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Reduce a state value to its unique, non-empty entries in staged order: upload tokens, and the
     * public-disk paths it selects from the offered images.
     *
     * @return list<string>
     */
    public function normalizePaths(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $entries = array_values(array_unique(array_filter(
            $state,
            fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        )));

        if ($entries === []) {
            return [];
        }

        $offeredPaths = $this->getPhotoPaths();

        return array_values(array_filter(
            $entries,
            fn (string $entry): bool => str_starts_with($entry, self::UPLOAD_TOKEN_PREFIX) || in_array($entry, $offeredPaths, true),
        ));
    }
}
