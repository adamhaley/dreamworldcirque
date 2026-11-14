<?php

namespace App\Services\Prospects;

use App\Ai\Agents\ProspectReplyGenerator;
use App\Enums\ProspectStatus;
use App\Models\FaqItem;
use App\Models\Prospect;
use App\Models\ReplyExample;
use Illuminate\Support\Str;

class ProspectReplyGenerationService
{
    public function generate(Prospect $prospect): Prospect
    {
        $response = app(ProspectReplyGenerator::class)->prompt($this->prompt($prospect));

        $reply = is_string($response['reply'] ?? null) ? trim($response['reply']) : '';

        $prospect->update([
            'generated_reply' => $reply,
            'final_reply' => $reply,
            'generation_reason' => is_string($response['reason'] ?? null)
                ? $response['reason']
                : 'Generator did not provide a usable reason.',
            'generated_at' => now(),
            'status' => ProspectStatus::DraftReady,
        ]);

        return $prospect->refresh();
    }

    // The FAQ list is small (19 entries as of writing) — inlining the whole thing
    // every call is simpler and cheaper than a retrieval step, and guarantees the
    // model never misses something relevant. Revisit if the FAQ grows large.
    private function prompt(Prospect $prospect): string
    {
        return sprintf(
            <<<'PROMPT'
Draft a reply to this inquiry.

FAQ knowledge base:
%s

Jena's past replies (copy the voice, never the facts):
%s

Inquiry:
Name: %s
Form type: %s
Interests: %s
Message: %s
PROMPT,
            $this->formatFaqItems(),
            $this->formatReplyExamples(),
            $prospect->name ?? '(not given)',
            $prospect->form_type ?? '(not given)',
            $prospect->interests !== null && $prospect->interests !== [] ? implode(', ', $prospect->interests) : '(none)',
            Str::limit((string) $prospect->message, 1000),
        );
    }

    private function formatFaqItems(): string
    {
        $items = FaqItem::query()->orderBy('sort_order')->get();

        if ($items->isEmpty()) {
            return 'No FAQ entries are available.';
        }

        return $items
            ->map(fn (FaqItem $item, int $index): string => sprintf(
                "Q%d: %s\nA%d: %s",
                $index + 1,
                $item->question,
                $index + 1,
                strip_tags($item->answer),
            ))
            ->implode("\n\n");
    }

    private function formatReplyExamples(): string
    {
        $examples = ReplyExample::query()->active()->get();

        if ($examples->isEmpty()) {
            return 'No examples are available.';
        }

        return $examples
            ->map(fn (ReplyExample $example, int $index): string => sprintf(
                "--- Example %d ---\nTheir message:\n%s\n\nJena's reply:\n%s",
                $index + 1,
                $example->inquiry ?: '(not recorded)',
                $example->reply,
            ))
            ->implode("\n\n");
    }
}
