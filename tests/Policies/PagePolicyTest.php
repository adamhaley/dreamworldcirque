<?php

namespace Tests\Policies;

use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PagePolicy::class)]
class PagePolicyTest extends TestCase
{
    public static function deletionProvider(): Generator
    {
        yield 'editor deleting a standard page' => [false, PageTemplate::Standard, true];
        yield 'editor deleting a system page' => [false, PageTemplate::Gallery, false];
        yield 'admin deleting a system page' => [true, PageTemplate::Gallery, true];
    }

    #[Test]
    #[DataProvider('deletionProvider')]
    public function it_restricts_deleting_system_pages_to_admins(bool $isAdmin, PageTemplate $template, bool $expected): void
    {
        $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();
        $page = Page::factory()->template($template)->create();

        $this->assertSame($expected, $user->can('delete', $page));
    }

    #[Test]
    public function it_prevents_deleting_a_page_that_has_children(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Page::factory()->create();
        Page::factory()->create(['parent_id' => $parent->id]);

        $this->assertFalse($admin->can('delete', $parent));
    }

    public static function structureProvider(): Generator
    {
        yield 'editor on a service page' => [false, PageTemplate::Service, true];
        yield 'editor on the home page' => [false, PageTemplate::Home, false];
        yield 'admin on the home page' => [true, PageTemplate::Home, true];
    }

    #[Test]
    #[DataProvider('structureProvider')]
    public function it_restricts_changing_system_page_structure_to_admins(bool $isAdmin, PageTemplate $template, bool $expected): void
    {
        $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();
        $page = Page::factory()->template($template)->create();

        $this->assertSame($expected, $user->can('changeStructure', $page));
    }
}
