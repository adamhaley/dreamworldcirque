<?php

namespace Tests\Database\Seeders;

use App\Enums\ProspectStatus;
use App\Models\Prospect;
use Database\Seeders\ProspectSeeder;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectSeeder::class)]
class ProspectSeederTest extends TestCase
{
    private const CSV_PATH = 'docs/legacy-leads/dreamworldcircus_leads_unique_contacts.csv';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->removeFixtureCsv();

        parent::tearDown();
    }

    #[Test]
    public function it_imports_genuine_and_spam_rows_from_the_csv(): void
    {
        $this->writeFixtureCsv();

        $this->seed(ProspectSeeder::class);

        $this->assertSame(2, Prospect::query()->count());

        $spam = Prospect::query()->where('source_external_id', '1')->sole();
        $this->assertSame(ProspectStatus::Spam, $spam->status);
        $this->assertSame('Jane Spammer', $spam->name);

        $genuine = Prospect::query()->where('source_external_id', '2')->sole();
        $this->assertSame(ProspectStatus::PendingClassification, $genuine->status);
        $this->assertSame('John Prospect', $genuine->name);
        $this->assertSame('5551234567', $genuine->phone);
        $this->assertSame('Interested in a wedding performance.', $genuine->message);
        $this->assertSame(['Wedding'], $genuine->interests);
    }

    #[Test]
    public function it_skips_import_when_prospects_already_exist(): void
    {
        Prospect::factory()->create();
        $this->writeFixtureCsv();

        $this->seed(ProspectSeeder::class);

        $this->assertSame(1, Prospect::query()->count());
    }

    #[Test]
    public function it_skips_import_when_the_csv_is_absent(): void
    {
        $this->removeFixtureCsv();

        $this->seed(ProspectSeeder::class);

        $this->assertSame(0, Prospect::query()->count());
    }

    #[Test]
    public function it_bounds_legacy_scalar_fields_to_database_column_lengths(): void
    {
        $this->writeFixtureCsv(str_repeat('x', 300));

        $this->seed(ProspectSeeder::class);

        $this->assertSame(255, mb_strlen(Prospect::query()->where('source_external_id', '1')->sole()->name));
    }

    private function writeFixtureCsv(?string $spamName = null): void
    {
        $path = base_path(self::CSV_PATH);
        @mkdir(dirname($path), recursive: true);

        $rows = [
            ['date', 'form_type', 'name', 'email', 'message', 'website', 'all_fields', 'submission_count', 'likely_spam'],
            [
                '2026-01-01T00:00:00-06:00',
                'Get_A_Quote Form Submission',
                $spamName ?? 'Jane Spammer',
                'spam@example.com',
                '',
                'example.com',
                'Name: Jane Spammer; Email: spam@example.com; Wedding: Unchecked',
                '1',
                'yes',
            ],
            [
                '2026-01-02T00:00:00-06:00',
                'Get_A_Quote Form Submission',
                'John Prospect',
                'john@example.com',
                '',
                'example.com',
                'Name: John Prospect; Email: john@example.com; Custom: 5551234567; Custom (2): Interested in a wedding performance.; Wedding: Checked; Birthday Party: Unchecked',
                '1',
                'no',
            ],
        ];

        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function removeFixtureCsv(): void
    {
        $path = base_path(self::CSV_PATH);

        if (is_file($path)) {
            unlink($path);
        }
    }
}
