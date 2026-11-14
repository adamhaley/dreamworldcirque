<?php

namespace App\Models;

use Database\Factories\ReplyExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A real reply Jena sent to a past inquiry, used as a few-shot example so the
 * AI reply drafter writes in her voice.
 */
#[Fillable(['title', 'inquiry', 'reply', 'is_active', 'sort_order'])]
class ReplyExample extends Model
{
    /** @use HasFactory<ReplyExampleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<ReplyExample> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }
}
