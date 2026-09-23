<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['contact_email', 'phone', 'studio_address', 'facebook_url', 'instagram_url', 'linkedin_url', 'accent_color'])]
class SiteSetting extends Model
{
    /** The pink used sitewide when no custom accent color has been set. */
    public const DefaultAccentColor = '#ee0979';

    public static function current(): self
    {
        return self::query()->firstOrCreate();
    }
}
