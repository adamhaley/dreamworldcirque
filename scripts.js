(function () {
  const grids = document.querySelectorAll('[data-thumbnail-gallery]');
  if (!grids.length) return;

  grids.forEach(function (grid) {
    const modal = grid.parentElement.querySelector('[data-gallery-modal]');
    if (!modal || typeof modal.showModal !== 'function') return;

    const items = Array.from(grid.querySelectorAll('[data-gallery-index]'));
    const imgEl = modal.querySelector('[data-gallery-modal-img]');
    const captionEl = modal.querySelector('[data-gallery-modal-caption]');
    let activeIndex = 0;

    function show(index) {
      activeIndex = (index + items.length) % items.length;
      const item = items[activeIndex];
      const img = item.querySelector('img');
      imgEl.src = img.getAttribute('src');
      imgEl.alt = img.getAttribute('alt') || '';
      captionEl.textContent = item.dataset.caption || '';
    }

    items.forEach(function (item, index) {
      item.addEventListener('click', function () {
        show(index);
        modal.showModal();
      });
    });

    const closeButton = modal.querySelector('[data-gallery-close]');
    if (closeButton) {
      closeButton.addEventListener('click', function () {
        modal.close();
      });
    }

    const prevButton = modal.querySelector('[data-gallery-prev]');
    if (prevButton) {
      prevButton.addEventListener('click', function () {
        show(activeIndex - 1);
      });
    }

    const nextButton = modal.querySelector('[data-gallery-next]');
    if (nextButton) {
      nextButton.addEventListener('click', function () {
        show(activeIndex + 1);
      });
    }

    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        modal.close();
      }
    });

    modal.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowRight') show(activeIndex + 1);
      if (event.key === 'ArrowLeft') show(activeIndex - 1);
    });
  });
})();

// Gates the hero title's word-by-word reveal on the Beyond Wonderland font
// actually being loaded, instead of running on page load regardless — on a
// slow connection the animation could otherwise run (and finish) in the
// fallback font, then visibly swap faces right after. A timeout fallback
// still reveals the title if font loading takes unusually long or the
// Font Loading API check fails, so the hero never stays blank.
(function () {
  const title = document.querySelector('.hero-title-animated');
  if (!title) return;

  function reveal() {
    title.classList.add('fonts-ready');
  }

  if (!('fonts' in document)) {
    reveal();
    return;
  }

  const timeout = setTimeout(reveal, 2000);
  document.fonts.load('1em "Beyond Wonderland"').then(function () {
    clearTimeout(timeout);
    reveal();
  }, reveal);
})();

// Back-to-top visibility, the header shrink, and the hero video's parallax
// all react to scroll position. They're combined into a single listener,
// batched to one requestAnimationFrame per tick, instead of three separate
// scroll listeners each forcing their own layout/paint work — trackpad
// scrolling fires far more scroll events than the display can paint, and
// three independent unthrottled handlers was causing visible jank.
(function () {
  const backToTop = document.querySelector('.back-to-top-link');
  const backToTopLink = backToTop ? backToTop.querySelector('a') : null;
  const header = document.querySelector('.site-header');
  // Homepage has a looping video hero; subpages have a static image hero.
  // Only one of these exists on any given page, so the same parallax drives
  // whichever is present.
  const media = document.querySelector('.masthead-video, .masthead-image');
  const hero = document.querySelector('.gradient-masthead, .image-masthead');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const backToTopThreshold = 140;
  const headerEnterThreshold = 30;
  const headerExitThreshold = 10;
  const parallaxIntervalMs = 50;
  let heroHeight = hero ? hero.offsetHeight : 0;
  let hideTimer;
  let ticking = false;
  let lastParallaxRun = 0;
  let headerScrolled = false;

  function updateHeroHeight() {
    if (hero) heroHeight = hero.offsetHeight;
  }

  function syncBackToTop(scrollY) {
    if (!backToTop) return;

    if (scrollY > backToTopThreshold) {
      backToTop.classList.add('in');
      clearTimeout(hideTimer);
      backToTop.classList.add('visible');
      return;
    }

    backToTop.classList.remove('visible');
    hideTimer = window.setTimeout(() => backToTop.classList.remove('in'), 180);
  }

  function syncHeaderScrolled(scrollY) {
    if (!header) return;

    // Hysteresis, not a single threshold: momentum-scroll deceleration can
    // leave scrollY hovering right around one pixel value as it settles,
    // which would otherwise flip the class on/off repeatedly and restart
    // the CSS transition mid-flight each time — the exact "jerks right as
    // I stop scrolling" symptom. A gap between the enter/exit points means
    // a single settling position can't bounce across both.
    if (!headerScrolled && scrollY > headerEnterThreshold) {
      headerScrolled = true;
    } else if (headerScrolled && scrollY < headerExitThreshold) {
      headerScrolled = false;
    }

    header.classList.toggle('is-scrolled', headerScrolled);
  }

  function syncParallax(scrollY) {
    if (!media || !hero || reduceMotion) return;
    if (scrollY > heroHeight) return;

    // Throttled independently of the rAF batch above: this is the one write
    // per tick expensive enough (compositor transform on a large element)
    // that we want a hard ceiling on how often it can fire, not just a
    // once-per-frame cap. The CSS transition on the media's transform
    // eases the visual gap between these throttled updates.
    const now = performance.now();
    if (now - lastParallaxRun < parallaxIntervalMs) return;
    lastParallaxRun = now;

    // Capped so the max shift stays a known, small amount regardless of
    // hero height/viewport — the media's CSS overscan (top: -20vh) is
    // sized to cover exactly this much movement without revealing a gap.
    const parallaxScrollCap = 300;
    const offset = Math.min(scrollY, parallaxScrollCap) * 0.45;
    media.style.transform = 'translateY(' + offset + 'px)';
  }

  function update() {
    ticking = false;
    const scrollY = window.scrollY;
    syncBackToTop(scrollY);
    syncHeaderScrolled(scrollY);
    syncParallax(scrollY);
  }

  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(update);
  }

  if (backToTopLink) {
    backToTopLink.addEventListener('click', function (event) {
      event.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', updateHeroHeight, { passive: true });
  update();
})();

(function () {
  const root = document.querySelector('.chat-widget');
  if (!root) return;

  const input = root.querySelector('.chat-input');
  const sendButton = root.querySelector('.chat-send-button');
  const messages = root.querySelector('.chat-messages');
  const closeButton = root.querySelector('.chat-close-button');
  const reopenButton = root.querySelector('.chat-reopen-button');

  function addMessage(message, isUser) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chat-message ' + (isUser ? 'chat-user-message' : 'chat-bot-message');
    messageDiv.textContent = message;
    messages.appendChild(messageDiv);
    messages.scrollTop = messages.scrollHeight;
  }

  function sendMessage(message) {
    if (!message.trim()) return;

    addMessage(message, true);
    input.value = '';

    // No chat backend is wired up yet, so respond with a placeholder.
    window.setTimeout(function () {
      addMessage("Thanks for reaching out! Our chat assistant isn't connected yet — please use the contact form and we'll get back to you.", false);
    }, 400);
  }

  closeButton.addEventListener('click', function () {
    root.classList.remove('is-open');
  });

  reopenButton.addEventListener('click', function () {
    root.classList.add('is-open');
  });

  sendButton.addEventListener('click', function () {
    sendMessage(input.value);
  });

  input.addEventListener('keypress', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendMessage(input.value);
    }
  });
})();

(function () {
  const items = document.querySelectorAll('.faq-item');
  if (!items.length) return;

  items.forEach(function (item) {
    const summary = item.querySelector('summary');
    const answer = item.querySelector('.faq-answer');

    summary.addEventListener('click', function (event) {
      event.preventDefault();

      if (item.open) {
        answer.style.height = answer.scrollHeight + 'px';
        answer.getBoundingClientRect(); // force reflow so the next line animates
        answer.style.height = '0px';
        answer.addEventListener('transitionend', function onEnd() {
          item.open = false;
          answer.style.height = '';
          answer.removeEventListener('transitionend', onEnd);
        }, { once: true });
        return;
      }

      item.open = true;
      const targetHeight = answer.scrollHeight;
      answer.style.height = '0px';
      answer.getBoundingClientRect(); // force reflow so the next line animates
      answer.style.height = targetHeight + 'px';
      answer.addEventListener('transitionend', function onEnd() {
        // Leave it at "auto" (not "") so it stays open and reflows with
        // content/viewport changes, instead of snapping to the CSS default
        // of height:0 and immediately animating shut again.
        answer.style.height = 'auto';
        answer.removeEventListener('transitionend', onEnd);
      }, { once: true });
    });
  });
})();