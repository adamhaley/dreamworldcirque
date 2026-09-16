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

  @if ($page->quote)
  <section class="section">
    <div class="container">
      <x-quote :quote="$page->quote" class="surface-card" />
    </div>
  </section>
  @endif

  <x-cta-band :page="$page" />
</x-layouts.site>
