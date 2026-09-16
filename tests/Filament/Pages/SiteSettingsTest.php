<?php

namespace Tests\Filament\Pages;

use App\Filament\Pages\SiteSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SiteSettings::class)]
class SiteSettingsTest extends TestCase
{
    #[Test]
    public function it_saves_contact_details(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['phone' => '(310) 555-0100', 'contact_email' => 'info@dreamworldcirque.com'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('(310) 555-0100', SiteSetting::current()->phone);
    }

    #[Test]
    public function it_rejects_an_invalid_social_url(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['instagram_url' => 'not a url'])
            ->call('save')
            ->assertHasFormErrors(['instagram_url' => 'url']);
    }
}
