<x-layouts.site :page="$page">
  <x-masthead.image :page="$page" button-label="Email Us" :button-href="'mailto:'.$settings->contact_email" />

  <section class="section" id="contact">
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
      <x-contact-grid :settings="$settings">
        <x-contact-card kicker="Services" href="/services/">Browse what we offer</x-contact-card>
      </x-contact-grid>
      <x-contact-form form-type="Get_A_Quote Form Submission" />
    </div>
  </section>
</x-layouts.site>
