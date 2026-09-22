<?php

namespace Tests\Services\Faq;

use App\Enums\FaqMatchOutcome;
use App\Services\Faq\FaqMatchingService;
use Database\Seeders\ContentSeeder;
use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The actual tuning harness for FaqMatchingService's thresholds and
 * FaqTokenizer's stemming, run against the real seeded FAQ content and
 * keywords rather than synthetic fixtures. When a threshold or a keyword
 * list changes, this is the test that says whether real visitor phrasing
 * still lands on the right answer -- see database/seeders/data/faq-keywords.php.
 */
#[CoversNothing]
class FaqCorpusMatchingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentSeeder::class);
    }

    #[Test]
    #[DataProvider('phrasingProvider')]
    public function it_matches_real_visitor_phrasings_to_the_right_faq(string $phrasing, string $expectedQuestion): void
    {
        $result = app(FaqMatchingService::class)->match($phrasing);

        $this->assertContains(
            $result->outcome,
            [FaqMatchOutcome::Confident, FaqMatchOutcome::Ambiguous],
            "\"{$phrasing}\" should match or at least suggest an FAQ, got {$result->outcome->value}",
        );

        $matchedQuestions = array_map(fn ($match): string => $match->faq->question, $result->matches);

        $this->assertContains(
            $expectedQuestion,
            $matchedQuestions,
            "\"{$phrasing}\" should match \"{$expectedQuestion}\", got: ".implode(' | ', $matchedQuestions),
        );
    }

    public static function phrasingProvider(): Generator
    {
        yield 'travel typo' => ['do you travle to phoenix', 'Do you travel outside Los Angeles and Las Vegas?'];
        yield 'aerial typo' => ['how much ceiling height for aerail', 'How much space and ceiling height do you need for aerial performances?'];
        yield 'pricing casual' => ['whats your pricing', 'How much does Cirque entertainment cost?'];
        yield 'pricing ballpark' => ['ballpark budget?', 'How much does Cirque entertainment cost?'];
        yield 'weddings' => ['do you do weddings', 'What kinds of events does Dream World Cirque perform at?'];
        yield 'ceiling height' => ['how tall does the ceiling need to be', 'How much space and ceiling height do you need for aerial performances?'];
        yield 'insurance' => ['are you insured', 'Do you provide insurance?'];
        yield 'travel to a city' => ['do you travel to phoenix', 'Do you travel outside Los Angeles and Las Vegas?'];
        yield 'lead time' => ['how early should we book', 'How far in advance should we book?'];
        yield 'full show' => ['can you do a full show', 'Can Dream World Cirque create a full Cirque show?'];
        yield 'planners' => ['do you work with event planners', 'Can you work with our event planner, producer, venue, or creative team?'];
        yield 'costuming' => ['can you match our color scheme', 'Can you coordinate costumes with our theme or event design?'];
        // Not "how do i book" -- that collapses to the single token "book"
        // (how/do/i are all stopwords), which is a genuine coin-flip against
        // FAQ 16 ("How far in advance should we book?"): that question's own
        // text contains "book" too, giving it an extra weighted hit this FAQ
        // only gets once, via keywords. A realistic multi-word phrasing
        // doesn't collapse to that single-token tiebreak.
        yield 'getting started' => ["what's the process to book you", 'How do we get started?'];
        yield 'film work' => ['do you do commercials', 'Do you provide performers for film, television and commercial productions?'];
        yield 'training' => ['are your performers experienced', 'Are your performers professionally trained?'];
        yield 'customization' => ['can we customize the performance', 'Can you create completely custom entertainment for our event?'];
        yield 'no venue for aerial' => ['our venue has low ceilings', "What if our venue can't accommodate aerial performances?"];
    }
}
