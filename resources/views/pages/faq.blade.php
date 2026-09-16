<x-layouts.site :page="$page">
  <x-masthead.image :page="$page" />

  <section class="section">
    <div class="container">
      <div class="faq-list">
        @foreach ($faqItems as $item)
        <details class="surface-card faq-item">
          <summary>{{ $item->question }}</summary>
          <div class="faq-answer"><div class="faq-answer-inner">{!! $item->answer !!}</div></div>
        </details>
        @endforeach
      </div>
    </div>
  </section>

  <x-cta-band :page="$page" />
</x-layouts.site>
