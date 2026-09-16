<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasLabel;

enum SpotlightImageSide: string implements HasLabel
{
    use TranslatesLabels;

    case Left = 'left';
    case Right = 'right';
}
