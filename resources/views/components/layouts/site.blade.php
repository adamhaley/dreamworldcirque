@props(['page'])
<!DOCTYPE html>
<html lang="en">
  <head>
    <script>
      // Strips a landing #hash from the URL before the browser gets a
      // chance to jump to it natively -- that native jump happens against
      // whatever the page's layout is at that very early moment (before
      // the hero video, webfonts, and the logo wall's lazy images have
      // settled its true height), landing short partway down the page.
      // scripts.js then does its own single, correct scroll once
      // everything has actually settled (see its settleThenScrollTo), so
      // without this the visible result is a jarring stop-then-jump: a
      // brief, wrong landing spot followed by a second scroll to the real
      // one. Stashing the hash on window lets scripts.js still know where
      // to scroll once it runs.
      (function () {
        if (!location.hash) return;
        window.__pendingScrollHash = location.hash;
        history.replaceState(null, '', location.pathname + location.search);
      })();
    </script>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    @php
      $ogTitle = $page->meta_title ?: $page->title.' | Dream World Cirque';
      $ogDescription = $page->meta_description ?: 'Boutique aerial and cirque entertainment for weddings, corporate events, and branded activations.';
      $ogImageMeta = $page->resolvedOgImage();
      // dreamworldcircus.com (an alternate English spelling Adam also owns)
      // serves this same app directly rather than redirecting -- see
      // RedirectLegacyHostsToApex's own docblock. Always build the
      // canonical/OG URL from the one real domain, regardless of which host
      // actually served the request, so search engines and anything a
      // visitor shares both point at the single authoritative URL instead
      // of fragmenting across two live, independently-crawlable domains.
      $canonicalUrl = rtrim(config('app.url'), '/').$page->url();
      // Which hosts actually count as "this site" for analytics -- the real
      // domain plus its English-spelling twin, but never Jena's staging
      // subdomain, which shares this one app/config and would otherwise mix
      // her admin-work traffic into the same numbers once APP_NOINDEX is
      // flipped false for production.
      $productionHosts = [parse_url(config('app.url'), PHP_URL_HOST), 'dreamworldcircus.com'];
      $isProductionHost = in_array(request()->getHost(), $productionHosts, true);
    @endphp
    <title>{{ $ogTitle }}</title>
    @if ($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}" />
    @endif
    <link rel="canonical" href="{{ $canonicalUrl }}" />
    {{-- Drawn in the CMS accent color by FaviconController; the color doubles
         as the cache-buster, so changing it in Site Settings shows up in
         returning visitors' tabs instead of a stale cached icon. --}}
    @php($faviconVersion = ltrim($settings->accentColor(), '#'))
    <link rel="icon" href="/favicon-48.png?v={{ $faviconVersion }}" type="image/png" sizes="48x48" />
    <link rel="icon" href="/favicon-192.png?v={{ $faviconVersion }}" type="image/png" sizes="192x192" />
    <link rel="apple-touch-icon" href="/favicon-180.png?v={{ $faviconVersion }}" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Dream World Cirque" />
    <meta property="og:url" content="{{ $canonicalUrl }}" />
    <meta property="og:title" content="{{ $ogTitle }}" />
    <meta property="og:description" content="{{ $ogDescription }}" />
    <meta property="og:image" content="{{ $ogImageMeta['url'] }}" />
    @if ($ogImageMeta['width'] && $ogImageMeta['height'])
    <meta property="og:image:width" content="{{ $ogImageMeta['width'] }}" />
    <meta property="og:image:height" content="{{ $ogImageMeta['height'] }}" />
    @endif
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $ogTitle }}" />
    <meta name="twitter:description" content="{{ $ogDescription }}" />
    <meta name="twitter:image" content="{{ $ogImageMeta['url'] }}" />
    @if (config('site.google_analytics_id') && $isProductionHost)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('site.google_analytics_id') }}"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag() { dataLayer.push(arguments); }
      gtag('js', new Date());
      gtag('config', '{{ config('site.google_analytics_id') }}');
    </script>
    @endif
    {{-- The heading and body fonts, fetched ahead of the stylesheet so
         they're ready by first paint. Paired with font-display: fallback in
         styles.css, which is what stops the one-frame fallback-font flash on
         the subpage hero title when navigating between pages. --}}
    <link rel="preload" href="/assets/fonts/Catamaran-Latin.woff2" as="font" type="font/woff2" crossorigin />
    <link rel="preload" href="/assets/fonts/Lato-Regular-Latin.woff2" as="font" type="font/woff2" crossorigin />
    @stack('head')
    <link rel="stylesheet" href="/styles.css?v={{ filemtime(public_path('styles.css')) }}" />
    @if ($settings->accent_color)
    {{-- Overrides the pink baked into styles.css's :root -- kept as a tiny
         inline block rather than templating the whole stylesheet, since this
         is the only value in it that's ever CMS-controlled. --}}
    <style>:root { --color-accent: {{ $settings->accent_color }}; }</style>
    @endif
  </head>
  <body>
    <div class="site-shell">
      <header class="site-header">
        <div class="container header-inner">
          <a class="site-brand" href="/"><span class="brand-logo-swap"><img src="/assets/img/logo-white.png" alt="" class="brand-logo brand-logo-light" /><img src="/assets/img/logo.png" alt="" class="brand-logo brand-logo-dark" /></span><span class="brand-wordmark">Dream World Cirque</span></a>
          <nav class="site-nav" aria-label="Primary">
            @foreach ($navPages as $navPage)
              {{-- A nav_anchor page always jumps to that homepage section, from any
                   page -- not just when already on the homepage. On the homepage
                   itself the bare hash lets the in-page smooth-scroll handle it;
                   elsewhere it's prefixed with "/" so it's a real navigation to the
                   homepage, which the fresh-load hash-scroll handles (see
                   settleThenScrollTo in scripts.js). --}}
              @php($navHref = $navPage->nav_anchor ? ($page->template === \App\Enums\PageTemplate::Home ? $navPage->nav_anchor : '/'.$navPage->nav_anchor) : $navPage->url())
              @if ($navPage->children->isNotEmpty())
              <div class="nav-item has-dropdown">
                <a href="{{ $navHref }}">{{ $navPage->title }} <svg class="nav-caret" viewBox="0 0 20 20" aria-hidden="true"><path d="M4 7H16L10 15Z"/></svg></a>
                <ul class="nav-dropdown">@foreach ($navPage->children as $child)<li><a href="{{ $child->url() }}">{{ $child->title }}</a></li>@endforeach</ul>
              </div>
              @else
              <a href="{{ $navHref }}">{{ $navPage->title }}</a>
              @endif
            @endforeach
          </nav>
        </div>
      </header>
      <main>
        {{ $slot }}
      </main>
      <footer class="site-footer">
        <div class="container footer-inner">
          <div class="footer-social">
            @if ($settings->facebook_url)
            <a href="{{ $settings->facebook_url }}" aria-label="Facebook"><x-icon.facebook /></a>
            @endif
            @if ($settings->instagram_url)
            <a href="{{ $settings->instagram_url }}" aria-label="Instagram"><x-icon.instagram /></a>
            @endif
            @if ($settings->linkedin_url)
            <a href="{{ $settings->linkedin_url }}" aria-label="LinkedIn"><x-icon.linkedin /></a>
            @endif
            @if ($settings->contact_email)
            <a href="mailto:{{ $settings->contact_email }}" aria-label="Email"><x-icon.email /></a>
            @endif
          </div>
          <div class="footer-text"><p class="footer-copyright">&copy; {{ now()->year }} Dream World Cirque</p><p class="footer-tagline">A Boutique aerial and cirque entertainment company.</p><p class="footer-credit">Website by <a href="https://ahmedia.ai" target="_blank" rel="noopener noreferrer">AH Media</a></p></div>
        </div>
      </footer>
      <x-chat-widget />
      <div class="scroll-down-link" data-threshold="140">
        <a href="#" aria-label="Scroll past hero">
          <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 6H18L10 16L2 6Z"/></svg>
        </a>
      </div>
      <div class="back-to-top-link" data-threshold="140">
        <a href="#top" aria-label="Scroll back to top">
          <x-icon.back />
        </a>
      </div>
    </div>
    @stack('scripts')
    <script src="/scripts.js?v={{ filemtime(public_path('scripts.js')) }}"></script>
  </body>
</html>
