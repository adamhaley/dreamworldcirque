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
- For a sparse inquiry (little or no message), weigh the email address itself as a
  signal: a plausible human name in the local part, especially one that resembles the
  submitted name, suggests a real person even with nothing else to go on. A random or
  gobbledygook local part is a negative signal in the same situation. Treat this as one
  input among several, not a standalone verdict — a real inquiry can still arrive with a
  mismatched or unrelated-looking address, and a bot can register on a legitimate
  provider with a name-like local part.
- The email domain check (whether its mail servers accept mail at all) is a separate,
  weaker signal — it can rule out an obviously fake domain, but says nothing about
  whether the specific address is real, so a passing check does not on its own indicate
  a genuine inquiry.
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
