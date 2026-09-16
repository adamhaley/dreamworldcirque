@props(['pages'])
<div class="feature-grid">
  @foreach ($pages as $service)
  <article class="surface-card feature-card">
    <h3>{{ $service->title }}</h3>
    <p>{{ $service->card_excerpt }}</p>
    <a class="preview-link" href="{{ $service->url() }}" aria-label="Learn more"><x-icon.forward /></a>
  </article>
  @endforeach
</div>
