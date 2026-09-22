<?php

declare(strict_types=1);

namespace App\Enums;

enum FaqMatchOutcome: string
{
    /** One FAQ scored well and clearly beat the runner-up -- answer it directly. */
    case Confident = 'confident';

    /** Several FAQs scored close together -- offer them rather than guess. */
    case Ambiguous = 'ambiguous';

    /** Nothing scored well enough to show -- hand off to a human. */
    case NoMatch = 'no_match';
}
