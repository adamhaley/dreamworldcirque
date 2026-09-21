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
    <title>{{ $page->meta_title ?: $page->title.' | Dream World Cirque' }}</title>
    @if ($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}" />
    @endif
    @stack('head')
    <link rel="stylesheet" href="/styles.css" />
  </head>
  <body>
    <div class="site-shell">
      <header class="site-header">
        <div class="container header-inner">
          <a class="site-brand" href="/"><span class="brand-logo-swap"><img src="/assets/img/logo-white.png" alt="" class="brand-logo brand-logo-light" /><img src="/assets/img/logo.png" alt="" class="brand-logo brand-logo-dark" /></span><span class="brand-wordmark">Dream World Cirque</span></a>
          <nav class="site-nav" aria-label="Primary">
            @foreach ($navPages as $navPage)
              @php($navHref = $page->template === \App\Enums\PageTemplate::Home && $navPage->nav_anchor ? $navPage->nav_anchor : $navPage->url())
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
          <div class="footer-text"><p class="footer-copyright">&copy; {{ now()->year }} Dream World Cirque</p><p class="footer-tagline">A Boutique aerial and cirque entertainment company.</p></div>
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
    <script src="/scripts.js"></script>
  </body>
</html>
