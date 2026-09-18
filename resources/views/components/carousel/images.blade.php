@props(['photos'])
<div id="gallery-carousel" class="carousel-shell surface-card carousel slide" style="--carousel-media-height: clamp(28rem, 66vw, 46rem); --carousel-image-fit: contain" data-bs-touch="true" data-bs-ride="carousel" data-bs-interval="4500">
  <div class="carousel-viewport">
    <div class="carousel-inner carousel-track" aria-live="polite">
      @foreach ($photos as $photo)
      <div @class(['carousel-item', 'active' => $loop->first])>
        <figure class="carousel-slide">
          <figure class="carousel-media">
            <img src="/storage/{{ $photo->image }}" alt="{{ $photo->alt }}" />
          </figure>
          @if ($photo->caption)
          <div class="codette-carousel-caption">
            <p>{{ $photo->caption }}</p>
          </div>
          @endif
        </figure>
      </div>
      @endforeach
    </div>
    <button class="carousel-control-prev codette-carousel-control" type="button" data-bs-target="#gallery-carousel" data-bs-slide="prev" aria-label="Previous slide">
      <x-icon.forward class="codette-carousel-control-icon codette-carousel-control-icon-prev" />
    </button>
    <button class="carousel-control-next codette-carousel-control" type="button" data-bs-target="#gallery-carousel" data-bs-slide="next" aria-label="Next slide">
      <x-icon.forward class="codette-carousel-control-icon" />
    </button>
  </div>
  <div class="carousel-indicators codette-carousel-indicators">
    @foreach ($photos as $photo)<button @class(['carousel-indicator', 'active' => $loop->first]) type="button" data-bs-target="#gallery-carousel" data-bs-slide-to="{{ $loop->index }}" aria-label="Go to slide {{ $loop->iteration }}" @if ($loop->first) aria-current="true"@endif></button>@endforeach
  </div>
</div>
