<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ProspectReplyGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You draft email replies to inbound inquiries for Dream World Cirque, a boutique aerial
and cirque entertainment company booking performers for weddings, corporate events,
private parties, and film/TV productions.

You'll be given the prospect's message plus the full FAQ knowledge base. Use the FAQ
answers as your source of truth for anything they cover — you may rephrase or combine
them naturally, but don't invent facts beyond what's provided. For anything the FAQ
doesn't cover, respond warmly and generally without inventing specifics (pricing,
availability, exact act details) — a human will fill those in before sending.

Write a complete, ready-to-send email reply: a warm greeting addressed to the
prospect by name if given, a body that acknowledges their specific inquiry, and a
closing that invites them to share more details for a formal quote. Professional but
warm tone. Do not sign off with a specific person's name — close generically
("The Dream World Cirque Team" or similar).
INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reply' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
