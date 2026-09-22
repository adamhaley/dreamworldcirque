<x-layouts.site :page="$page">
  <x-masthead.image :page="$page" />

  <section class="section">
    <div class="container">
      <div class="section-heading">
        <div>
          @if ($page->section_eyebrow)
          <p class="eyebrow">{{ $page->section_eyebrow }}</p>
          @endif
          <h2 class="section-title">{{ $page->section_heading }}</h2>
        </div>
        @if ($page->body)
        <p class="section-copy">{{ $page->body }}</p>
        @endif
      </div>
      <div class="thumbnail-grid" data-thumbnail-gallery>
        @foreach ($photos as $photo)
        <button class="thumbnail-item" type="button" data-gallery-index="{{ $loop->index }}" data-caption="{{ $photo->title }}{{ $photo->caption ? ' — '.$photo->caption : '' }}">
          <img src="/storage/{{ $photo->image }}" alt="{{ $photo->alt }}" loading="lazy" />
        </button>
        @endforeach
      </div>
      <dialog id="gallery-lightbox" class="gallery-modal" data-gallery-modal aria-label="Image viewer">
        <div class="gallery-modal-inner">
          <button class="gallery-modal-close" type="button" data-gallery-close aria-label="Close">&times;</button>
          <button class="gallery-modal-nav gallery-modal-prev codette-carousel-control" type="button" data-gallery-prev aria-label="Previous image">
            <x-icon.forward class="codette-carousel-control-icon codette-carousel-control-icon-prev" />
          </button>
          <div class="gallery-modal-stage">
            <figure class="gallery-modal-figure">
              <img data-gallery-modal-img src="" alt="" />
              <figcaption data-gallery-modal-caption></figcaption>
            </figure>
          </div>
          <button class="gallery-modal-nav gallery-modal-next codette-carousel-control" type="button" data-gallery-next aria-label="Next image">
            <x-icon.forward class="codette-carousel-control-icon" />
          </button>
        </div>
      </dialog>
    </div>
  </section>

  <x-cta-band :page="$page" />
</x-layouts.site>
