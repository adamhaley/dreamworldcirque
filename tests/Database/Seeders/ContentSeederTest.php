<?php

namespace Tests\Database\Seeders;

use App\Models\Page;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContentSeeder::class)]
class ContentSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function it_imports_every_page_of_the_original_site(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(13, Page::query()->count());
    }

    #[Test]
    public function it_renders_every_imported_page(): void
    {
        $this->seed(ContentSeeder::class);

        Page::query()->get()->each(fn (Page $page) => $this->get($page->url())->assertOk());
    }

    #[Test]
    public function it_does_not_overwrite_existing_content(): void
    {
        Page::factory()->create(['title' => 'Edited in the CMS']);

        $this->seed(ContentSeeder::class);

        $this->assertSame(1, Page::query()->count());
    }
}
