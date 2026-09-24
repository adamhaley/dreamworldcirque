<?php

namespace Tests\Filament\Widgets;

use App\Filament\Widgets\ProspectClassificationCompositionChart;
use App\Models\User;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectClassificationCompositionChart::class)]
class ProspectClassificationCompositionChartTest extends TestCase
{
    public static function roleProvider(): Generator
    {
        yield 'admin' => [true, true];
        yield 'editor' => [false, false];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function it_restricts_the_classification_composition_chart_to_admins(bool $isAdmin, bool $expected): void
    {
        $this->actingAs($isAdmin ? User::factory()->admin()->create() : User::factory()->create());

        $this->assertSame($expected, ProspectClassificationCompositionChart::canView());
    }
}
