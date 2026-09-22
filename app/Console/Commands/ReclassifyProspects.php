<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProspectStatus;
use App\Jobs\ClassifyProspect;
use App\Models\Prospect;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prospects:reclassify {--status=pending_review : The prospect status to reclassify} {--dry-run : List matching prospects without making changes}')]
#[Description('Re-run AI classification for prospects with a given status')]
class ReclassifyProspects extends Command
{
    public function handle(): int
    {
        $status = ProspectStatus::tryFrom((string) $this->option('status'));

        if ($status === null) {
            $this->error(sprintf(
                'Unknown status [%s]. Valid: %s',
                $this->option('status'),
                implode(', ', array_map(fn (ProspectStatus $case): string => $case->value, ProspectStatus::cases())),
            ));

            return self::FAILURE;
        }

        $prospects = Prospect::query()->where('status', $status->value)->get(['id', 'name', 'email']);

        if ($prospects->isEmpty()) {
            $this->info("No prospects with status [{$status->value}].");

            return self::SUCCESS;
        }

        $this->info("{$prospects->count()} prospect(s) with status [{$status->value}].");

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Name', 'Email'], $prospects->map(fn (Prospect $prospect): array => [
                $prospect->id, $prospect->name, $prospect->email,
            ])->all());

            return self::SUCCESS;
        }

        if ($status !== ProspectStatus::PendingReview) {
            $this->warn("Reclassifying [{$status->value}] prospects resets them to pending_classification -- any progress past that stage (a drafted or sent reply, etc.) is lost until they're re-reviewed.");
        }

        if (! $this->confirm("Reclassify {$prospects->count()} prospect(s)?")) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($prospects->count());
        $bar->start();

        foreach ($prospects as $prospect) {
            Prospect::query()->whereKey($prospect->id)->update(['status' => ProspectStatus::PendingClassification->value]);
            ClassifyProspect::dispatch($prospect->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Dispatched -- check the queue worker for progress.');

        return self::SUCCESS;
    }
}
