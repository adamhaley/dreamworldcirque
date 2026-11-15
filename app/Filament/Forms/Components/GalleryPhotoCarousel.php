<?php

namespace App\Filament\Forms\Components;

use App\Models\GalleryPhoto;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Swipeable carousel of Gallery photos whose state is a list of selected GalleryPhoto ids.
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
            $component->state(self::normalizeIds($state));
        });

        $this->dehydrateStateUsing(static fn (mixed $state): array => self::normalizeIds($state));

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
     * @return list<array{id: int, url: string, alt: string, label: string}>
     */
    public function getPhotos(): array
    {
        return GalleryPhoto::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (GalleryPhoto $photo): array => [
                'id' => $photo->id,
                'url' => Storage::disk('public')->url($photo->image),
                'alt' => (string) ($photo->alt ?? ''),
                'label' => (string) ($photo->alt ?: $photo->title ?: basename($photo->image)),
            ])
            ->values()
            ->all();
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
     * @return list<int>
     */
    private static function normalizeIds(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $ids = array_map(
            fn (mixed $id): int => (int) $id,
            array_filter($state, fn (mixed $id): bool => is_numeric($id) && (int) $id > 0),
        );

        return array_values(array_unique($ids));
    }
}
