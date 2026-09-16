@props(['kicker', 'href' => null])
<article class="surface-card contact-card">
  <p class="contact-kicker">{{ $kicker }}</p>
  <p class="contact-value">@if ($href)<a href="{{ $href }}">{{ $slot }}</a>@else{{ $slot }}@endif</p>
</article>
