<?php

namespace Tests\Views;

use App\Models\FaqItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    #[Test]
    public function it_exposes_only_public_faq_fields_and_safely_encodes_html(): void
    {
        $faq = FaqItem::factory()->create([
            'question' => 'Can I ask about </script><script>alert(1)</script>?',
            'answer' => '<p>Yes, <strong>ask us</strong>.</p>',
            'keywords' => 'editorial-only-keyword',
        ]);

        $html = (string) $this->blade('<x-chat-widget />');

        preg_match('/<script[^>]*class="chat-faq-data">(.*?)<\/script>/s', $html, $matches);
        $this->assertSame([
            ['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer],
        ], json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('editorial-only-keyword', $html);
    }

    #[Test]
    public function it_renders_without_any_faqs(): void
    {
        $this->blade('<x-chat-widget />')->assertSee('class="chat-faq-data">[]</script>', false);
    }
}
