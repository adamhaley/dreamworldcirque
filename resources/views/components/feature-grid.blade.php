@props(['pages'])
@php
  // Matches each service to the heroicon closest to its subject matter,
  // in place of the generic forward arrow every card used to share.
  $serviceIcons = [
      'acts-shows' => 'sparkles',
      'ambient-entertainment' => 'musical-note',
      'weddings-private-events' => 'heart',
      'corporate-events' => 'briefcase',
      'film-tv-productions' => 'film',
      'equipment-rentals' => 'wrench-screwdriver',
      'costume-rentals' => 'swatch',
  ];
@endphp
<div class="feature-grid">
  @foreach ($pages as $service)
  <article class="surface-card feature-card">
    <h3>{{ $service->title }}</h3>
    <p>{{ $service->card_excerpt }}</p>
    <a class="preview-link" href="{{ $service->url() }}" aria-label="Learn more">
      <x-dynamic-component :component="'icon.'.($serviceIcons[$service->slug] ?? 'forward')" />
    </a>
  </article>
  @endforeach
</div>
