@props(['photos', 'page'])
@php($carouselId = 'service-carousel-'.$page->id)
<div id="{{ $carouselId }}" class="carousel-shell service-carousel surface-card @if ($photos->count() > 1) carousel slide carousel-fade @endif" @if ($photos->count() > 1) data-bs-touch="true" data-bs-ride="carousel" data-bs-interval="4500" @endif>
  <div class="carousel-viewport">
    <div class="carousel-inner carousel-track" aria-live="polite">
      @foreach ($photos as $photo)
        <div @class(['carousel-item', 'active' => $loop->first])>
          <figure class="carousel-slide">
            <div class="carousel-media service-carousel-media">
              <img src="{{ asset('storage/'.$photo->image) }}" alt="{{ $photo->alt }}" loading="lazy" />
              @if ($photo->is_placeholder)
                <span class="service-carousel-placeholder">Placeholder for {{ $page->title }}</span>
              @endif
            </div>
            @if ($photo->caption)
              <figcaption class="codette-carousel-caption"><p>{{ $photo->caption }}</p></figcaption>
            @endif
          </figure>
        </div>
      @endforeach
    </div>
    @if ($photos->count() > 1)
      <button class="carousel-control-prev codette-carousel-control" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="prev" aria-label="Previous {{ $page->title }} photo">
        <x-icon.forward class="codette-carousel-control-icon codette-carousel-control-icon-prev" />
      </button>
      <button class="carousel-control-next codette-carousel-control" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="next" aria-label="Next {{ $page->title }} photo">
        <x-icon.forward class="codette-carousel-control-icon" />
      </button>
    @endif
  </div>
  @if ($photos->count() > 1)
    <div class="carousel-indicators codette-carousel-indicators">
      @foreach ($photos as $photo)
        <button @class(['carousel-indicator', 'active' => $loop->first]) type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide-to="{{ $loop->index }}" aria-label="Go to {{ $page->title }} photo {{ $loop->iteration }}" @if ($loop->first) aria-current="true" @endif></button>
      @endforeach
    </div>
  @endif
</div>
