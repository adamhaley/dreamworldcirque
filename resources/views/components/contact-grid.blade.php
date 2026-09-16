@props(['settings'])
<div class="contact-grid">
  @if ($settings->contact_email)
  <x-contact-card kicker="Email" :href="'mailto:'.$settings->contact_email">{{ $settings->contact_email }}</x-contact-card>
  @endif
  @if ($settings->phone)
  <x-contact-card kicker="Phone" :href="'tel:+1'.preg_replace('/\D/', '', $settings->phone)">{{ $settings->phone }}</x-contact-card>
  @endif
  @if ($settings->studio_address)
  <x-contact-card kicker="Studio">{{ $settings->studio_address }}</x-contact-card>
  @endif
  {{ $slot }}
</div>
