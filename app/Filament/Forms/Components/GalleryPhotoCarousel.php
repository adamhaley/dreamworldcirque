<?php

namespace App\Filament\Forms\Components;

use App\Models\GalleryPhoto;
use App\Models\Page;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Swipeable carousel of the website's images: Gallery photos (in gallery order), then each Page's
 * hero, circle and card images. Each image is keyed by its public-disk path and offered once; the
 * field's state is the list of selected public-disk paths, limited to the images it offers.
 */
class GalleryPhotoCarousel extends Field
{
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

        $this->dehydrateStateUsing(static fn (GalleryPhotoCarousel $component, mixed $state): array => $component->normalizePaths($state));

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
     * @return list<array{key: string, name: string, url: string|null}>
     */
    public function getStagedUploads(): array
    {
        $statePath = $this->evaluate($this->stagedUploadsStatePath);

        if (blank($statePath)) {
            return [];
        }

        $files = $this->evaluate(fn (Get $get): mixed => $get($statePath));

        if (! is_array($files)) {
            return [];
        }

        $uploads = [];

        foreach ($files as $key => $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $uploads[] = [
                    'key' => (string) $key,
                    'name' => $file->getClientOriginalName(),
                    'url' => $this->temporaryUploadUrl($file),
                ];
            } elseif (is_string($file) && $file !== '') {
                $uploads[] = [
                    'key' => (string) $key,
                    'name' => basename($file),
                    'url' => $this->storedUploadUrl($file),
                ];
            }
        }

        return $uploads;
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
     * Reduce a state value to the unique, non-empty public-disk paths it selects from the offered images,
     * in the carousel's offered order.
     *
     * @return list<string>
     */
    public function normalizePaths(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $paths = array_values(array_unique(array_filter(
            $state,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        )));

        if ($paths === []) {
            return [];
        }

        return array_values(array_intersect($this->getPhotoPaths(), $paths));
    }
}
