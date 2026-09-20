<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProspectStatus: string implements HasColor, HasLabel
{
    use TranslatesLabels;

    case PendingClassification = 'pending_classification';
    case PendingReview = 'pending_review';
    case Spam = 'spam';
    case Unanswerable = 'unanswerable';
    case Valid = 'valid';
    case DraftGenerating = 'draft_generating';
    case DraftReady = 'draft_ready';
    case Replied = 'replied';

    public function getColor(): string
    {
        return match ($this) {
            self::PendingClassification => 'gray',
            self::PendingReview => 'info',
            self::Spam => 'gray',
            self::Unanswerable => 'warning',
            self::Valid => 'success',
            self::DraftGenerating => 'info',
            self::DraftReady => 'warning',
            self::Replied => 'success',
        };
    }

    // Rows here mean "AI or human is expected to move this forward" — drives the
    // table's conditional poll() so only in-flight rows keep refreshing.
    public function isActive(): bool
    {
        return in_array($this, [self::PendingClassification, self::DraftGenerating], true);
    }
}
