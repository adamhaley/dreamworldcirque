<?php

namespace Database\Seeders;

use App\Enums\ProspectStatus;
use App\Models\Prospect;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProspectSeeder extends Seeder
{
    private const CSV_PATH = 'docs/legacy-leads/dreamworldcircus_leads_unique_contacts.csv';

    public function run(): void
    {
        if (Prospect::query()->exists()) {
            $this->command?->warn('Prospects already exist; skipping legacy lead import.');

            return;
        }

        $path = base_path(self::CSV_PATH);

        if (! is_file($path)) {
            $this->command?->warn('Legacy leads CSV not found at '.self::CSV_PATH.'; skipping import.');

            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        $externalId = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $externalId++;
            $record = array_combine($header, $row);
            $this->importRow($record, $externalId);
        }

        fclose($handle);
    }

    /** @param array<string, string> $record */
    private function importRow(array $record, int $externalId): void
    {
        $fields = $this->parseAllFields($record['all_fields'] ?? '');
        $isSpam = strtolower(trim($record['likely_spam'] ?? '')) === 'yes';

        Prospect::create([
            'source' => 'legacy_contact_form',
            'source_external_id' => (string) $externalId,
            'submitted_at' => Carbon::parse($record['date']),
            'form_type' => $record['form_type'] ?: null,
            'name' => $record['name'] ?: null,
            'email' => $record['email'] ?: null,
            'phone' => $fields['phone'],
            'website' => $record['website'] ?: null,
            'message' => $fields['message'],
            'interests' => $fields['interests'] === [] ? null : $fields['interests'],
            'submission_count' => (int) ($record['submission_count'] ?: 1),
            'status' => $isSpam ? ProspectStatus::Spam : ProspectStatus::PendingClassification,
            'review_note' => $isSpam ? 'Likely spam per legacy mailbox recovery (2026-09-02 automated flagging).' : null,
            'raw_payload' => [
                'all_fields' => $record['all_fields'] ?? null,
                'likely_spam' => $record['likely_spam'] ?? null,
            ],
        ]);
    }

    /** @return array{phone: ?string, message: ?string, interests: list<string>} */
    private function parseAllFields(string $allFields): array
    {
        $phone = null;
        $messageParts = [];
        $interests = [];

        foreach (explode('; ', $allFields) as $pair) {
            if (! str_contains($pair, ': ')) {
                continue;
            }

            [$label, $value] = explode(': ', $pair, 2);
            $label = trim($label);
            $value = trim($value);

            if ($value === 'Checked') {
                $interests[] = $label;

                continue;
            }

            if ($value === 'Unchecked') {
                continue;
            }

            if ($label === 'Custom' && $phone === null) {
                $phone = $value;

                continue;
            }

            if (str_starts_with($label, 'Custom')) {
                $messageParts[] = $value;
            }
        }

        return [
            'phone' => $phone,
            'message' => $messageParts === [] ? null : implode("\n\n", $messageParts),
            'interests' => $interests,
        ];
    }
}
