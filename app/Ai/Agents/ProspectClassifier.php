<?php

namespace App\Ai\Agents;

use App\Enums\ProspectClassification;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ProspectClassifier implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You classify inbound inquiries for Dream World Cirque, a boutique aerial and cirque
entertainment company that books performers for weddings, corporate events, private
parties, and film/TV productions.

Classes:
- valid: a genuine inquiry from someone interested in booking or learning about services.
- spam: bot-generated link drops, unrelated marketing/SEO pitches, or clearly automated noise.
- unanswerable: a real message, but not something this business can act on (job applications,
  vendor solicitations, personal messages unrelated to booking an event).
- needs_human: ambiguous, fragmented, or not safe to classify confidently.

Rules:
- Be conservative. Use needs_human when uncertain rather than guessing.
- Do not draft a reply here — only classify.
INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'classification' => $schema->string()
                ->enum(array_map(fn (ProspectClassification $case): string => $case->value, ProspectClassification::cases()))
                ->required(),
            'confidence' => $schema->integer()->min(0)->max(100)->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
