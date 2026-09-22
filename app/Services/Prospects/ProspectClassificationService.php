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
or unanswerable, rather than copying any single example blindly.

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
     * @return Collection<int, Prospect>
     */
    private function reviewedExamplesFor(Prospect $prospect): Collection
    {
        $examples = $this->reviewedExampleQuery($prospect)
            ->whereNotNull('classification')
            ->where(function (Builder $query): void {
                foreach (ProspectClassification::cases() as $classification) {
                    $query->orWhere(function (Builder $query) use ($classification): void {
                        $query
                            ->where('classification', $classification->value)
                            ->where('status', '!=', $classification->value);
                    });
                }
            })
            ->latest('reviewed_at')
            ->limit(3)
            ->get();

        foreach ([ProspectStatus::Valid, ProspectStatus::Spam, ProspectStatus::Unanswerable] as $status) {
            $examples = $examples->merge(
                $this->reviewedExampleQuery($prospect)
                    ->where('status', $status->value)
                    ->latest('reviewed_at')
                    ->limit(2)
                    ->get(),
            );
        }

        return $examples->unique('id')->take(self::MaxTrainingExamples)->values();
    }

    /** @return Builder<Prospect> */
    private function reviewedExampleQuery(Prospect $prospect): Builder
    {
        return Prospect::query()
            ->select(['id', 'name', 'email', 'message', 'classification', 'status', 'reviewed_at', 'reviewed_by_user_id'])
            ->whereKeyNot($prospect->id)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('reviewed_by_user_id');
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
                $example->status->getLabel(),
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
