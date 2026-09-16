<?php

namespace App\Models;

use App\Enums\SpotlightImageSide;
use Database\Factories\SpotlightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property list<string>|null $bullets
 * @property list<array<string, string>>|null $buttons
 */
#[Fillable(['anchor', 'eyebrow', 'heading', 'body', 'bullets', 'image', 'image_alt', 'image_side', 'buttons', 'sort_order'])]
class Spotlight extends Model
{
    /** @use HasFactory<SpotlightFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bullets' => 'array',
            'buttons' => 'array',
            'image_side' => SpotlightImageSide::class,
        ];
    }
}
