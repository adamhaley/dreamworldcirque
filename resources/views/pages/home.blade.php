<x-layouts.site :page="$page">
  @push('head')
  <link rel="preload" href="/assets/fonts/Birmingham.ttf" as="font" type="font/ttf" crossorigin />
  @endpush

  <header class="gradient-masthead" id="top">
    <video class="masthead-video" autoplay muted loop playsinline poster="/assets/video/hero-poster.jpg" aria-hidden="true">
      <source src="/assets/video/hero.mp4" type="video/mp4" />
    </video>
    <div class="masthead-video-overlay"></div>
    <div class="container">
      <p class="eyebrow">Welcome to the world of…</p>
      <h1 class="display-title hero-title-animated">@foreach (explode(' ', $page->hero_heading ?: $page->title) as $word)<span class="hero-title-word">{{ $word }}</span>{{ $loop->last ? '' : ' ' }}@endforeach</h1>
      @if ($page->hero_subheading)
      <p class="masthead-subheading">{{ $page->hero_subheading }}</p>
      @endif
      <div class="button-row"><x-button href="#contact">Request a Quote</x-button></div>
    </div>
    <div class="masthead-circle masthead-circle-1"></div>
    <div class="masthead-circle masthead-circle-2"></div>
    <div class="masthead-circle masthead-circle-3"></div>
  </header>

  @foreach ($spotlights as $spotlight)
  <x-spotlight
    :anchor="$spotlight->anchor"
    :eyebrow="$spotlight->eyebrow"
    :heading="$spotlight->heading"
    :body="$spotlight->body"
    :bullets="$spotlight->bullets"
    :buttons="$spotlight->buttons"
    :image="$spotlight->image"
    :image-alt="$spotlight->image_alt"
    :image-left="$spotlight->image_side === \App\Enums\SpotlightImageSide::Left"
  />
  @endforeach

  <x-logo-wall />

  @if ($quotes->isNotEmpty())
  <section class="section">
    <div class="container">
      <x-carousel.quotes :quotes="$quotes" />
    </div>
  </section>
  @endif

  @if ($services)
  <section class="section" id="services">
    <div class="container">
      <p class="eyebrow">What We Do</p>
      <div class="section-heading">
        <div>
          <h2 class="section-title">Seven ways to bring Dream World Cirque to your event.</h2>
          <p class="section-copy">Book a single act or a full production — every category below is a dedicated service.</p>
        </div>
      </div>
      <x-feature-grid :pages="$services->children" />
    </div>
  </section>
  @endif

  @if ($carouselPhotos->isNotEmpty())
  <section class="section" id="gallery">
    <div class="container">
      <div class="section-heading">
        <div>
          <p class="eyebrow">Showcase</p>
          <h2 class="section-title">A look at recent performances.</h2>
        </div>
        <p class="section-copy">A cross-section of acts from corporate galas, weddings, and branded activations.</p>
        <div class="button-row"><x-button href="/gallery/" variant="secondary" arrow>View Full Gallery</x-button></div>
      </div>
      <x-carousel.images :photos="$carouselPhotos" />
    </div>
  </section>
  @endif

  <section class="section" id="contact">
    <div class="container">
      <div class="section-heading">
        <div>
          <p class="eyebrow">Contact</p>
          <h2 class="section-title">Tell us about your event.</h2>
        </div>
        <p class="section-copy">Share a few details and we&#39;ll design a custom cirque performance package for your venue, audience, and budget.</p>
      </div>
      <x-contact-grid :settings="$settings">
        <x-contact-card kicker="Get a Quote" href="#contact">Start your event inquiry</x-contact-card>
      </x-contact-grid>
    </div>
  </section>

  @push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @endpush
</x-layouts.site>
