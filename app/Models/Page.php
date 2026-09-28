<?php

namespace App\Models;

use App\Actions\GenerateOgImageAction;
use App\Enums\PageTemplate;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property list<string>|null $bullets
 * @property list<array<string, string>>|null $buttons
 */
#[Fillable([
    'parent_id', 'title', 'slug', 'template', 'sort_order', 'is_published', 'show_in_nav', 'nav_anchor',
    'meta_title', 'meta_description', 'hero_heading', 'hero_subheading', 'hero_image', 'og_image', 'og_cta',
    'section_eyebrow', 'section_heading', 'body', 'bullets', 'buttons', 'circle_image', 'circle_image_alt',
    'quote_id', 'card_excerpt', 'cta_eyebrow', 'cta_heading', 'cta_button_label',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => PageTemplate::class,
            'bullets' => 'array',
            'buttons' => 'array',
            'is_published' => 'boolean',
            'show_in_nav' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->path = $page->template === PageTemplate::Home
                ? ''
                : ltrim(($page->parent?->path ?? '').'/'.$page->slug, '/');
        });

        static::saved(function (Page $page): void {
            if ($page->wasChanged('path')) {
                $page->children->each(fn (Page $child): bool => $child->setRelation('parent', $page)->save());
            }

            // isDirty(), not wasChanged(): syncChanges() only runs on update, so
            // wasChanged() is unreliable for freshly created models here -- isDirty()
            // still reflects pre-sync state correctly for both create and update.
            if ($page->isDirty(['hero_image', 'hero_heading', 'hero_subheading', 'og_cta', 'title'])) {
                (new GenerateOgImageAction)($page);
            }
        });
    }

    /** Storage path (on the "public" disk) for a page's generated OG image composite. */
    public static function ogImageStoragePathFor(int $id): string
    {
        return "og-images/{$id}.jpg";
    }

    /**
     * The hero+CTA composite generated for this page, if one exists. Used as
     * the OG image fallback when no manual og_image override is uploaded.
     */
    public function generatedOgImagePath(): ?string
    {
        $path = self::ogImageStoragePathFor($this->id);

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function url(): string
    {
        return $this->path === '' ? '/' : '/'.$this->path.'/';
    }

    /** @return list<int> */
    public function descendantIds(): array
    {
        return $this->children->flatMap(fn (Page $child): array => [$child->id, ...$child->descendantIds()])->all();
    }
}
