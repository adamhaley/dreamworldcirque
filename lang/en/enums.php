<?php

use App\Enums\ButtonStyle;
use App\Enums\PageTemplate;
use App\Enums\SpotlightImageSide;
use App\Enums\UserRole;

return [
    ButtonStyle::class => [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
    ],
    PageTemplate::class => [
        'contact' => 'Contact',
        'faq' => 'FAQ',
        'gallery' => 'Gallery',
        'home' => 'Home',
        'service' => 'Service',
        'services_index' => 'Services index',
        'standard' => 'Standard',
    ],
    SpotlightImageSide::class => [
        'left' => 'Left',
        'right' => 'Right',
    ],
    UserRole::class => [
        'admin' => 'Admin',
        'editor' => 'Editor',
    ],
];
