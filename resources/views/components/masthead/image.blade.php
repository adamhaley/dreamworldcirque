@props(['page', 'buttonLabel' => 'Get a Quote', 'buttonHref' => '/#contact'])
<header class="image-masthead">
  @if ($page->hero_image)
  <img class="masthead-image" src="/storage/{{ $page->hero_image }}" alt="" aria-hidden="true" />
  @endif
  <div class="masthead-image-overlay"></div>
  <div class="container">
    <h1 class="display-title">{{ $page->hero_heading ?: $page->title }}</h1>
    @if ($page->hero_subheading)
    <p class="masthead-subheading">{{ $page->hero_subheading }}</p>
    @endif
    <div class="button-row"><a class="hero-back-link" href="{{ $page->parent?->url() ?? '/' }}" aria-label="Back to previous page" data-history-back><x-icon.back /></a><x-button :href="$buttonHref">{{ $buttonLabel }}</x-button></div>
  </div>
</header>
