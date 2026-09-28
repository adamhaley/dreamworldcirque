<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\GenerateOgImageAction;
use App\Models\Page;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pages:generate-og-images')]
#[Description('Backfill or regenerate the auto-composited OG share image for every page with a hero image')]
class GenerateOgImages extends Command
{
    public function handle(GenerateOgImageAction $generate): int
    {
        $pages = Page::query()->whereNotNull('hero_image')->where('hero_image', '!=', '')->get();

        if ($pages->isEmpty()) {
            $this->info('No pages with a hero image.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($pages->count());
        $bar->start();

        $generated = 0;

        foreach ($pages as $page) {
            if ($generate($page) !== null) {
                $generated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Generated {$generated} of {$pages->count()} OG image(s).");

        return self::SUCCESS;
    }
}
