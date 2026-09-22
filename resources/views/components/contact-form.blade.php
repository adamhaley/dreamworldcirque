@props(['formType' => 'Home Form Submission'])
@php
  $interests = \App\Models\Page::query()
      ->where('template', \App\Enums\PageTemplate::Service)
      ->orderBy('sort_order')
      ->pluck('title');
@endphp
<form class="surface-card contact-form" action="{{ route('contact.store') }}" method="post" novalidate>
  @csrf
  <input type="hidden" name="form_type" value="{{ $formType }}" />

  <div class="contact-form-row">
    <div class="contact-form-field">
      <label class="contact-form-label" for="contact-name-{{ $formType }}">Name</label>
      <input class="contact-form-input" type="text" id="contact-name-{{ $formType }}" name="name" required />
      <p class="contact-form-error" data-field="name" hidden></p>
    </div>
    <div class="contact-form-field">
      <label class="contact-form-label" for="contact-email-{{ $formType }}">Email</label>
      <input class="contact-form-input" type="email" id="contact-email-{{ $formType }}" name="email" required />
      <p class="contact-form-error" data-field="email" hidden></p>
    </div>
  </div>

  <div class="contact-form-field">
    <label class="contact-form-label" for="contact-phone-{{ $formType }}">Phone <span class="contact-form-optional">(optional)</span></label>
    <input class="contact-form-input" type="tel" id="contact-phone-{{ $formType }}" name="phone" />
    <p class="contact-form-error" data-field="phone" hidden></p>
  </div>

  @if ($interests->isNotEmpty())
  <div class="contact-form-field">
    <span class="contact-form-label">What are you interested in? <span class="contact-form-optional">(optional)</span></span>
    <div class="contact-form-checkboxes">
      @foreach ($interests as $interest)
      <label class="contact-form-checkbox">
        <input type="checkbox" name="interests[]" value="{{ $interest }}" />
        {{ $interest }}
      </label>
      @endforeach
    </div>
  </div>
  @endif

  <div class="contact-form-field">
    <label class="contact-form-label" for="contact-message-{{ $formType }}">Tell us about your event</label>
    <textarea class="contact-form-input contact-form-textarea" id="contact-message-{{ $formType }}" name="message" rows="4" required></textarea>
    <p class="contact-form-error" data-field="message" hidden></p>
  </div>

  {{-- Hidden from real visitors via CSS (.contact-form-honeypot); a bot that
       fills every visible field fills this one too, which is exactly what
       marks it as a bot server-side (see StoreContactSubmissionRequest). --}}
  <div class="contact-form-honeypot" aria-hidden="true">
    <label for="contact-website-{{ $formType }}">Website</label>
    <input type="text" id="contact-website-{{ $formType }}" name="website" tabindex="-1" autocomplete="off" />
  </div>

  <button type="submit" class="button button-primary contact-form-submit">Send Inquiry</button>
  <p class="contact-form-status" hidden></p>
</form>
