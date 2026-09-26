<x-layouts.site :page="$page">
  <x-masthead.image :page="$page" button-label="Email Us" :button-href="'mailto:'.$settings->contact_email" />

  <section class="section">
    <div class="container">
      <div id="contact" class="scroll-target section-heading">
        <div>
          @if ($page->section_eyebrow)
          <p class="eyebrow">{{ $page->section_eyebrow }}</p>
          @endif
          <h2 class="section-title">Get in touch.</h2>
        </div>
      </div>
      <x-contact-grid :settings="$settings">
        <x-contact-card kicker="Services" href="/services/">Browse what we offer</x-contact-card>
      </x-contact-grid>
      <h3 id="quote" class="scroll-target contact-form-heading">{{ $page->section_heading }}</h3>
      @if ($page->body)
      <p class="section-copy contact-form-copy">{!! nl2br(e($page->body)) !!}</p>
      @endif
      <x-contact-form form-type="Get_A_Quote Form Submission" />
    </div>
  </section>
</x-layouts.site>
