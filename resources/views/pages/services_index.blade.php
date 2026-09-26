<x-layouts.site :page="$page">
  <x-masthead.image :page="$page" />

  <section class="section">
    <div class="container">
      @if ($page->section_eyebrow)
      <p class="eyebrow">{{ $page->section_eyebrow }}</p>
      @endif
      <div class="section-heading">
        <div>
          <h2 class="section-title">{{ $page->section_heading }}</h2>
          @if ($page->body)
          <p class="section-copy">{!! nl2br(e($page->body)) !!}</p>
          @endif
        </div>
      </div>
      <x-feature-grid :pages="$page->children" />
    </div>
  </section>

  <x-cta-band :page="$page" />
</x-layouts.site>
