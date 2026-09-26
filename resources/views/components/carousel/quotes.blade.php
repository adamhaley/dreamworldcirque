@props(['quotes'])
<div id="quotes-carousel" class="carousel-shell surface-card carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="8000" data-bs-pause="false">
  <div class="carousel-viewport">
    <div class="carousel-inner carousel-track" aria-live="polite">
      @foreach ($quotes as $quote)
      <div @class(['carousel-item', 'active' => $loop->first])>
        <x-quote :quote="$quote" />
      </div>
      @endforeach
    </div>
  </div>
  <div class="carousel-indicators codette-carousel-indicators">
    @foreach ($quotes as $quote)<button @class(['carousel-indicator', 'active' => $loop->first]) type="button" data-bs-target="#quotes-carousel" data-bs-slide-to="{{ $loop->index }}" aria-label="Go to quote {{ $loop->iteration }}" @if ($loop->first) aria-current="true"@endif></button>@endforeach
  </div>
</div>
