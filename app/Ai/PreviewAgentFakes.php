<?php

namespace App\Ai;

use App\Ai\Agents\ProspectClassifier;
use App\Ai\Agents\ProspectReplyGenerator;

class PreviewAgentFakes
{
    /**
     * Fake the prospect agents with canned responses when running in the
     * preview environment without an OpenAI key.
     */
    public static function register(): bool
    {
        if (! static::shouldFake()) {
            return false;
        }

        ProspectClassifier::fake(fn (): array => [
            'classification' => 'valid',
            'confidence' => 90,
            'reason' => 'Preview fake: looks like a genuine booking inquiry.',
        ]);

        ProspectReplyGenerator::fake(fn (): array => [
            'reply' => "Hi love! Thank you so much for reaching out, we would be thrilled to be part of your event. Tell me a little more about the date and the vibe you're dreaming of and I'll put together some ideas! xo Jena",
            'reason' => 'Preview fake: warm canned reply in Jena\'s voice.',
        ]);

        return true;
    }

    /**
     * Determine whether the canned fakes should be registered.
     */
    protected static function shouldFake(): bool
    {
        return app()->environment('preview')
            && blank(config('ai.providers.openai.key'))
            && blank(config('services.openai.api_key'));
    }
}
