@props(['quote'])
<blockquote {{ $attributes->class('quote-band') }}>
  <p class="quote-text">"{{ $quote->body }}"</p>
  <footer class="quote-attribution">&mdash; {{ $quote->name }}@if ($quote->affiliation), {{ $quote->affiliation }}@endif</footer>
</blockquote>
