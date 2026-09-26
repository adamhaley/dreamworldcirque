@props(['page'])
@if ($page->cta_heading)
<section class="section">
  <div class="container">
    <div class="surface-card cta-band">
      <div>
        @if ($page->cta_eyebrow)
        <p class="eyebrow">{{ $page->cta_eyebrow }}</p>
        @endif
        <h2 class="section-title">{{ $page->cta_heading }}</h2>
        <p class="section-copy">Response within 24 hours for most inquiries.</p>
      </div>
      <div class="button-row"><x-button href="/contact/" arrow data-contact-reveal aria-expanded="false" aria-controls="cta-contact-form">{{ $page->cta_button_label ?: 'Request a Quote' }}</x-button></div>
    </div>
    <div id="cta-contact-form" class="scroll-target" hidden>
      <x-contact-form form-type="Get_A_Quote Form Submission" />
    </div>
  </div>
</section>
@endif
