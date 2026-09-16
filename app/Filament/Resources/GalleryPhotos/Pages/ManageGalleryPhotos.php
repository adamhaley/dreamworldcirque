<?php

namespace App\Filament\Resources\GalleryPhotos\Pages;

use App\Filament\Resources\GalleryPhotos\GalleryPhotoResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGalleryPhotos extends ManageRecords
{
    protected static string $resource = GalleryPhotoResource::class;
}
