<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Faq\FaqMatchingService;
use App\Services\Faq\FaqTokenizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('faq:match {question* : The visitor question to score against the FAQ corpus}')]
#[Description('Show how the chatbot scores a question against the FAQs (read-only tuning aid)')]
class FaqMatch extends Command
{
    public function handle(FaqMatchingService $matcher, FaqTokenizer $tokenizer): int
    {
        $question = implode(' ', (array) $this->argument('question'));
        $result = $matcher->match($question);

        $this->newLine();
        $this->line("  <fg=gray>query</>   {$question}");
        $this->line('  <fg=gray>tokens</>  '.(implode(' ', $tokenizer->tokenize($question)) ?: '(none)'));
        $this->line('  <fg=gray>outcome</> '.$result->outcome->value);
        $this->newLine();

        if ($result->matches === []) {
            $this->warn('  Nothing scored high enough — this would hand off to lead capture.');

            return self::SUCCESS;
        }

        $this->table(
            ['Score', 'ID', 'Question'],
            array_map(fn ($match): array => [
                number_format($match->score, 3),
                (string) $match->faq->id,
                $match->faq->question,
            ], $result->matches),
        );

        return self::SUCCESS;
    }
}
