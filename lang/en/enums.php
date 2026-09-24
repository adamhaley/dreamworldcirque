<?php

use App\Enums\ButtonStyle;
use App\Enums\PageTemplate;
use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
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
    ProspectClassification::class => [
        'needs_human' => 'Needs human',
        'spam' => 'Spam',
        'unanswerable' => 'Unanswerable',
        'valid' => 'Valid',
    ],
    ProspectStatus::class => [
        'draft_generating' => 'Drafting reply…',
        'draft_ready' => 'Draft ready',
        'pending_classification' => 'Pending classification',
        'pending_review' => 'Pending review',
        'replied' => 'Replied',
        'spam' => 'Spam',
        'unanswerable' => 'Unanswerable',
        'valid' => 'Valid',
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
