@props(['eyebrow' => null, 'heading', 'body' => null, 'bullets' => [], 'buttons' => [], 'image' => null, 'imageAlt' => '', 'imageLeft' => false, 'anchor' => null])
<section class="section"@if ($anchor) id="{{ $anchor }}"@endif>
  <div class="container spotlight-grid {{ $imageLeft ? 'spotlight-media-left' : '' }}">
    @if ($image)
    <figure class="spotlight-media spotlight-media-circle">
      <img src="/storage/{{ $image }}" alt="{{ $imageAlt }}" />
    </figure>
    @endif
    <div class="spotlight-copy">
      @if ($eyebrow)
      <p class="eyebrow">{{ $eyebrow }}</p>
      @endif
      <h2 class="section-title">{{ $heading }}</h2>
      @if ($body)
      <p class="section-copy">{{ $body }}</p>
      @endif
      @if (filled($bullets))
      <ul class="bullet-list">@foreach ($bullets as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
      @endif
      @if (filled($buttons))
      <div class="button-row">@foreach ($buttons as $button)<x-button :href="$button['url']" :variant="$button['style']" :arrow="$button['style'] === \App\Enums\ButtonStyle::Secondary->value">{{ $button['label'] }}</x-button>@endforeach</div>
      @endif
    </div>
  </div>
</section>
