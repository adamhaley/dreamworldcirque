<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['contact_email', 'phone', 'studio_address', 'facebook_url', 'instagram_url', 'linkedin_url'])]
class SiteSetting extends Model
{
    public static function current(): self
    {
        return self::query()->firstOrCreate();
    }
}
