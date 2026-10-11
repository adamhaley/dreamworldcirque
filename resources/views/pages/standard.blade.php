<x-layouts.site :page="$page">
  <x-masthead.image :page="$page" />

  @if ($page->section_heading)
  <x-spotlight
    :eyebrow="$page->section_eyebrow"
    :heading="$page->section_heading"
    :body="$page->body"
    :bullets="$page->bullets"
    :buttons="$page->buttons"
    :image="$page->circle_image"
    :image-alt="$page->circle_image_alt"
  />
  @endif

  @isset($servicePhotos)
    @if ($servicePhotos->isNotEmpty())
      <section class="section">
        <div class="container">
          <div class="section-heading">
            <div>
              <p class="eyebrow">{{ $page->title }}</p>
              <h2 class="section-title">See it in action.</h2>
            </div>
          </div>
          <x-carousel.service-images :photos="$servicePhotos" :page="$page" />
        </div>
      </section>
    @endif
  @endisset

  @if ($page->quote)
  <section class="section">
    <div class="container">
      <x-quote :quote="$page->quote" class="surface-card" />
    </div>
  </section>
  @endif

  <x-cta-band :page="$page" />
</x-layouts.site>
