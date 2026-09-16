<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasLabel;

enum PageTemplate: string implements HasLabel
{
    use TranslatesLabels;

    case Contact = 'contact';
    case Faq = 'faq';
    case Gallery = 'gallery';
    case Home = 'home';
    case Service = 'service';
    case ServicesIndex = 'services_index';
    case Standard = 'standard';

    // Templates the site structure depends on; only admins may delete or re-template them.
    public function isSystem(): bool
    {
        return ! in_array($this, [self::Service, self::Standard], true);
    }

    public function view(): string
    {
        return 'pages.'.$this->value;
    }
}
