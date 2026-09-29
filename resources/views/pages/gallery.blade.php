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
        <p class="section-copy">{!! nl2br(e($page->body)) !!}</p>
        @endif
      </div>
      <div class="thumbnail-grid">
        @foreach ($photos as $photo)
        <button class="thumbnail-item" type="button" data-bs-toggle="modal" data-bs-target="#gallery-lightbox" data-gallery-slide-to="{{ $loop->index }}">
          <img src="/storage/{{ $photo->image }}" alt="{{ $photo->alt }}" loading="lazy" />
        </button>
        @endforeach
      </div>
      @php $firstPhoto = $photos->first(); @endphp
      <div class="modal fade gallery-modal" id="gallery-lightbox" tabindex="-1" aria-label="Image viewer" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
          <div class="modal-content">
            <button type="button" class="gallery-modal-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            <div id="gallery-carousel" class="carousel slide gallery-modal-carousel" data-bs-interval="false">
              <div class="carousel-inner">
                @foreach ($photos as $photo)
                <div @class(['carousel-item', 'active' => $loop->first]) data-caption="{{ $photo->title }}{{ $photo->caption ? ' — '.$photo->caption : '' }}">
                  <figure class="gallery-modal-figure">
                    <img src="/storage/{{ $photo->image }}" alt="{{ $photo->alt }}" />
                  </figure>
                </div>
                @endforeach
              </div>
              <button class="carousel-control-prev codette-carousel-control gallery-modal-nav gallery-modal-prev" type="button" data-bs-target="#gallery-carousel" data-bs-slide="prev" aria-label="Previous image">
                <x-icon.forward class="codette-carousel-control-icon codette-carousel-control-icon-prev" />
              </button>
              <button class="carousel-control-next codette-carousel-control gallery-modal-nav gallery-modal-next" type="button" data-bs-target="#gallery-carousel" data-bs-slide="next" aria-label="Next image">
                <x-icon.forward class="codette-carousel-control-icon" />
              </button>
            </div>
            {{-- Lives outside .carousel-inner deliberately, so Bootstrap's slide
                 transform on .carousel-item never touches it -- it cross-fades
                 in place instead (see the slide.bs.carousel listener below). --}}
            <figcaption class="gallery-modal-caption" data-gallery-caption>{{ $firstPhoto?->title }}{{ $firstPhoto?->caption ? ' — '.$firstPhoto->caption : '' }}</figcaption>
          </div>
        </div>
      </div>
    </div>
  </section>

  <x-cta-band :page="$page" />

  @push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Bootstrap's Modal/Carousel handle focus trapping, backdrop, ESC-to-close,
    // touch/swipe, and z-index layering natively -- this only needs to jump the
    // carousel to whichever thumbnail was actually clicked before the modal opens.
    (function () {
      const modalEl = document.getElementById('gallery-lightbox');
      const carouselEl = document.getElementById('gallery-carousel');
      const captionEl = document.querySelector('[data-gallery-caption]');
      if (!modalEl || !carouselEl || typeof bootstrap === 'undefined') return;

      const carousel = bootstrap.Carousel.getOrCreateInstance(carouselEl, { ride: false, touch: true });

      modalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const index = trigger ? parseInt(trigger.dataset.gallerySlideTo, 10) : 0;
        carousel.to(index);
      });

      modalEl.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowRight') carousel.next();
        if (event.key === 'ArrowLeft') carousel.prev();
      });

      // The caption sits outside .carousel-inner (see the blade comment
      // above it), so it never slides with the image -- cross-fade its text
      // in place instead, timed to land roughly mid-slide.
      if (captionEl) {
        const fadeMs = 200;
        captionEl.style.transitionDuration = fadeMs + 'ms';

        carouselEl.addEventListener('slide.bs.carousel', function (event) {
          const nextCaption = event.relatedTarget ? event.relatedTarget.dataset.caption || '' : '';
          captionEl.classList.add('is-hidden');
          setTimeout(function () {
            captionEl.textContent = nextCaption;
            captionEl.classList.remove('is-hidden');
          }, fadeMs);
        });
      }
    })();
  </script>
  @endpush
</x-layouts.site>
