<?php

namespace Tests\Models;

use App\Enums\PageTemplate;
use App\Models\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Page::class)]
class PageTest extends TestCase
{
    #[Test]
    public function it_gives_the_home_page_the_root_url(): void
    {
        $home = Page::factory()->home()->create();

        $this->assertSame('/', $home->url());
    }

    #[Test]
    public function it_nests_a_child_url_under_its_parent(): void
    {
        $services = Page::factory()->create(['slug' => 'services']);

        $child = Page::factory()->create(['parent_id' => $services->id, 'slug' => 'aerial-acts']);

        $this->assertSame('/services/aerial-acts/', $child->url());
    }

    #[Test]
    public function it_recomputes_descendant_paths_when_a_parent_slug_changes(): void
    {
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        $child = Page::factory()->create(['parent_id' => $services->id, 'slug' => 'aerial-acts']);
        $grandchild = Page::factory()->create(['parent_id' => $child->id, 'slug' => 'silks']);

        $services->update(['slug' => 'what-we-do']);

        $this->assertSame('what-we-do/aerial-acts/silks', $grandchild->fresh()->path);
    }

    #[Test]
    public function it_lists_all_descendant_ids(): void
    {
        $parent = Page::factory()->create();
        $child = Page::factory()->create(['parent_id' => $parent->id]);
        $grandchild = Page::factory()->create(['parent_id' => $child->id]);

        $ids = $parent->descendantIds();

        $this->assertEqualsCanonicalizing([$child->id, $grandchild->id], $ids);
    }
}
