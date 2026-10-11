<?php

namespace App\Models;

use Database\Factories\ServicePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['page_id', 'image', 'alt', 'caption', 'sort_order', 'is_placeholder'])]
class ServicePhoto extends Model
{
    /** @use HasFactory<ServicePhotoFactory> */
    use HasFactory;

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_placeholder' => 'boolean'];
    }
}
