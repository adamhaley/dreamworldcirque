<?php

namespace App\Services\Prospects;

use App\Ai\Agents\ProspectClassifier;
use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProspectClassificationService
{
    private const MaxTrainingExamples = 8;

    private const MaxMatchingExamples = 3;

    public function __construct(private ProspectEmailDomainChecker $emailDomainChecker) {}

    public function classify(Prospect $prospect): Prospect
    {
        $trainingExamples = $this->reviewedExamplesFor($prospect);
        $emailMxValid = $prospect->email !== null ? $this->emailDomainChecker->hasMailExchanger($prospect->email) : null;

        $response = app(ProspectClassifier::class)->prompt($this->prompt($prospect, $trainingExamples, $emailMxValid));

        $prospect->update([
            'email_mx_valid' => $emailMxValid,
            'classification' => $this->classification($response['classification'] ?? null),
            'classification_confidence' => $this->confidence($response['confidence'] ?? null),
            'classification_reason' => is_string($response['reason'] ?? null)
                ? $response['reason']
                : 'Classifier did not provide a usable reason.',
            'classified_at' => now(),
            'status' => ProspectStatus::PendingReview,
        ]);

        return $prospect->refresh();
    }

    /**
     * @param  Collection<int, Prospect>  $trainingExamples
     */
    private function prompt(Prospect $prospect, Collection $trainingExamples, ?bool $emailMxValid): string
    {
        return sprintf(
            <<<'PROMPT'
Classify this inbound inquiry.

Use the human-reviewed examples as guidance. The human decision is correct for each
example — use them to calibrate what this business considers a valid inquiry, spam,
or unanswerable, rather than copying any single example blindly. Examples that share this
inquiry's name, email or message are listed first, because the same sender often
repeats a pattern a human already judged. Weigh such an example heavily only when
this message follows the same pattern; a shared name alone is common among real
people and must not turn a detailed, genuine booking inquiry into spam.

Human-reviewed examples:
%s

Inquiry:
Name: %s
Email: %s
Form type: %s
Interests: %s
Email domain accepts mail: %s
Message: %s

A failing email domain check means the address's domain has no mail servers willing
to accept mail -- it does not by itself mean the inquiry is fake. A genuine inquiry
can still have a typo'd email address, and a bot can register on a legitimate free
provider and pass this check. Weigh it alongside the message content and interests,
not as a standalone verdict.
PROMPT,
            $this->formatTrainingExamples($trainingExamples),
            $prospect->name ?? '(not given)',
            $prospect->email ?? '(not given)',
            $prospect->form_type ?? '(not given)',
            $prospect->interests !== null && $prospect->interests !== [] ? implode(', ', $prospect->interests) : '(none)',
            match ($emailMxValid) {
                true => 'yes',
                false => 'no',
                null => 'no email given',
            },
            Str::limit((string) $prospect->message, 1000),
        );
    }

    /**
     * Reviews of the same sender or message come first, however old they are,
     * so a repeat spammer stays recognised after newer reviews push its
     * correction out of the recency slots that follow.
     *
     * @return Collection<int, Prospect>
     */
    private function reviewedExamplesFor(Prospect $prospect): Collection
    {
        $examples = $this->matchingReviewedExamples($prospect)->merge(
            $this->reviewedExampleQuery($prospect)
                ->whereNotNull('classification')
                ->whereColumn('classification', '!=', 'human_classification')
                ->latest('reviewed_at')
                ->limit(3)
                ->get(),
        );

        foreach ([ProspectClassification::Valid, ProspectClassification::Spam, ProspectClassification::Unanswerable] as $decision) {
            $examples = $examples->merge(
                $this->reviewedExampleQuery($prospect)
                    ->where('human_classification', $decision->value)
                    ->latest('reviewed_at')
                    ->limit(2)
                    ->get(),
            );
        }

        return $examples->unique('id')->take(self::MaxTrainingExamples)->values();
    }

    /**
     * @return Collection<int, Prospect>
     */
    private function matchingReviewedExamples(Prospect $prospect): Collection
    {
        $matchingFields = array_filter(
            ['email' => $prospect->email, 'name' => $prospect->name, 'message' => $prospect->message],
            fn (?string $value): bool => $value !== null && trim($value) !== '',
        );

        if ($matchingFields === []) {
            return new Collection;
        }

        return $this->reviewedExampleQuery($prospect)
            ->where(function (Builder $query) use ($matchingFields): void {
                foreach ($matchingFields as $column => $value) {
                    $query->orWhere($column, $value);
                }
            })
            ->latest('reviewed_at')
            ->limit(self::MaxMatchingExamples)
            ->get();
    }

    /** @return Builder<Prospect> */
    private function reviewedExampleQuery(Prospect $prospect): Builder
    {
        return Prospect::query()
            ->select(['id', 'name', 'email', 'message', 'classification', 'human_classification', 'reviewed_at'])
            ->whereKeyNot($prospect->id)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('reviewed_by_user_id')
            ->whereNotNull('human_classification');
    }

    /**
     * @param  Collection<int, Prospect>  $trainingExamples
     */
    private function formatTrainingExamples(Collection $trainingExamples): string
    {
        if ($trainingExamples->isEmpty()) {
            return 'No human-reviewed examples are available yet.';
        }

        return $trainingExamples
            ->map(fn (Prospect $example, int $index): string => sprintf(
                <<<'EXAMPLE'
                Example %d:
                Name: %s
                Email: %s
                Message: %s
                Original AI classification: %s
                Correct human decision: %s
                EXAMPLE,
                $index + 1,
                $example->name ?? '(not given)',
                $example->email ?? '(not given)',
                Str::limit((string) $example->message, 300),
                $example->classification?->getLabel() ?? 'Unclassified',
                $example->human_classification?->getLabel() ?? 'Unreviewed',
            ))
            ->implode("\n\n");
    }

    private function classification(mixed $classification): ProspectClassification
    {
        return is_string($classification)
            ? (ProspectClassification::tryFrom($classification) ?? ProspectClassification::NeedsHuman)
            : ProspectClassification::NeedsHuman;
    }

    private function confidence(mixed $confidence): int
    {
        return is_numeric($confidence) ? max(0, min(100, (int) $confidence)) : 0;
    }
}
