// Re-corrects a scroll-to-anchor jump once the page's true height settles.
// Covers two cases that both land short and leave a gap above the target:
//
// 1. A fresh page load with a #hash in the URL (e.g. a "Get a Quote" CTA
//    linking here from another page) — the browser jumps very early, before
//    the hero's video/fonts have finished loading and settled the page's
//    final height. The inline script at the top of the layout's <head>
//    already strips the hash before the browser gets a chance to act on
//    it (see its own comment for why), stashing it on
//    window.__pendingScrollHash instead, so this only ever performs one
//    clean scroll rather than a visible wrong-then-right double jump.
// 2. An in-page click on a same-page anchor (e.g. the hero's own "Request a
//    Quote" button) — scroll-behavior: smooth computes the scroll distance
//    once, at click time. If the logo wall's lazily-loaded client images
//    haven't started fetching yet (they're off-screen on a fresh visit),
//    they load in and push everything below them down mid-scroll, but the
//    already-in-flight scroll never re-targets.
//
// Re-running scrollIntoView() once fonts and any still-loading images
// settle corrects the drift in both cases.
(function () {
  function settleThenScrollTo(target) {
    const pendingImages = Array.from(document.images).filter(function (img) { return !img.complete; });

    // The logo wall's images are loading="lazy" and only start fetching
    // once scrolled near -- which never happens while we're deliberately
    // holding scroll position until settled right here. Left alone,
    // waiting on them below would deadlock: nothing ever triggers their
    // load, so this promise chain would never resolve and the page would
    // never scroll at all. Forcing them to load now breaks that deadlock.
    pendingImages.forEach(function (img) {
      if (img.loading === 'lazy') img.loading = 'eager';
    });

    Promise.all([
      document.fonts ? document.fonts.ready : Promise.resolve(),
      new Promise(function (resolve) { requestAnimationFrame(function () { requestAnimationFrame(resolve); }); }),
    ].concat(pendingImages.map(function (img) {
      return new Promise(function (resolve) {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
      });
    }))).then(function () {
      target.scrollIntoView();
    });
  }

  if (window.__pendingScrollHash) {
    const initialTarget = document.querySelector(window.__pendingScrollHash);
    if (initialTarget) {
      window.addEventListener('load', function () { settleThenScrollTo(initialTarget); });
    }
  }

  document.addEventListener('click', function (event) {
    const link = event.target.closest('a[href^="#"]');
    if (!link || link.closest('.scroll-down-link, .back-to-top-link')) return;

    const hash = link.getAttribute('href');
    if (!hash || hash.length < 2) return;

    const target = document.querySelector(hash);
    if (!target) return;

    settleThenScrollTo(target);
  });
})();

(function () {
  const grids = document.querySelectorAll('[data-thumbnail-gallery]');
  if (!grids.length) return;

  grids.forEach(function (grid) {
    const modal = grid.parentElement.querySelector('[data-gallery-modal]');
    if (!modal || typeof modal.showModal !== 'function') return;

    const items = Array.from(grid.querySelectorAll('[data-gallery-index]'));
    let figureEl = modal.querySelector('.gallery-modal-figure');
    const stageEl = modal.querySelector('.gallery-modal-stage');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let activeIndex = 0;
    let isAnimating = false;

    function normalizedIndex(index) {
      return (index + items.length) % items.length;
    }

    function populateFigure(figure, index) {
      const item = items[index];
      const img = item.querySelector('img');
      const figureImg = figure.querySelector('img');
      const figureCaption = figure.querySelector('figcaption');
      figureImg.src = img.getAttribute('src');
      figureImg.alt = img.getAttribute('alt') || '';
      figureCaption.textContent = item.dataset.caption || '';
    }

    function updateImage(index) {
      activeIndex = normalizedIndex(index);
      populateFigure(figureEl, activeIndex);
    }

    function preloadImage(index) {
      const source = items[index].querySelector('img').getAttribute('src');

      const image = new Image();
      image.src = source;

      if (typeof image.decode === 'function') {
        return image.decode().catch(function () {});
      }

      return new Promise(function (resolve) {
        if (image.complete) {
          resolve();
          return;
        }

        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
      });
    }

    function show(index, direction) {
      if (!direction || reduceMotion || typeof figureEl.animate !== 'function') {
        updateImage(index);
        return;
      }

      if (isAnimating) return;
      isAnimating = true;

      const targetIndex = normalizedIndex(index);
      const directionMultiplier = direction === 'next' ? 1 : -1;

      preloadImage(targetIndex).then(function () {
        if (!modal.open) return;

        const incomingFigure = figureEl.cloneNode(true);
        populateFigure(incomingFigure, targetIndex);
        stageEl.appendChild(incomingFigure);

        const timing = { duration: 420, easing: 'cubic-bezier(0.4, 0, 0.2, 1)', fill: 'forwards' };

        const outgoingAnimation = figureEl.animate([
          { transform: 'translateX(0)' },
          { transform: 'translateX(' + (-100 * directionMultiplier) + 'vw)' },
        ], timing);

        const incomingAnimation = incomingFigure.animate([
          { transform: 'translateX(' + (100 * directionMultiplier) + 'vw)' },
          { transform: 'translateX(0)' },
        ], timing);

        return Promise.all([outgoingAnimation.finished, incomingAnimation.finished]).then(function () {
          figureEl.remove();
          outgoingAnimation.cancel();
          incomingAnimation.cancel();
          figureEl = incomingFigure;
          activeIndex = targetIndex;
        });
      }).finally(function () {
        isAnimating = false;
      });
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
        show(activeIndex - 1, 'previous');
      });
    }

    const nextButton = modal.querySelector('[data-gallery-next]');
    if (nextButton) {
      nextButton.addEventListener('click', function () {
        show(activeIndex + 1, 'next');
      });
    }

    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        modal.close();
      }
    });

    modal.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowRight') show(activeIndex + 1, 'next');
      if (event.key === 'ArrowLeft') show(activeIndex - 1, 'previous');
    });
  });
})();

// Gates the hero title's word-by-word reveal on the Birmingham font
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
  document.fonts.load('1em "Birmingham"').then(function () {
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
  const scrollDown = document.querySelector('.scroll-down-link');
  const scrollDownLink = scrollDown ? scrollDown.querySelector('a') : null;
  const header = document.querySelector('.site-header');
  // Homepage has a looping video hero; subpages have a static image hero.
  // Only one of these exists on any given page, so the same parallax drives
  // whichever is present.
  const media = document.querySelector('.masthead-video, .masthead-image');
  const hero = document.querySelector('.gradient-masthead, .image-masthead');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const arrowEnterThreshold = 160;
  const arrowExitThreshold = 100;
  const headerEnterThreshold = 30;
  const headerExitThreshold = 10;
  const parallaxIntervalMs = 50;
  let heroHeight = hero ? hero.offsetHeight : 0;
  let ticking = false;
  let lastParallaxRun = 0;
  let headerScrolled = false;
  let pastHero = false;

  function updateHeroHeight() {
    if (hero) heroHeight = hero.offsetHeight;
  }

  // Back-to-top and the scroll-down arrow share one slot and one boolean,
  // with a hysteresis gap between the enter/exit points (same fix the
  // header shrink effect needed — momentum-scroll deceleration can leave
  // scrollY hovering right around a single value as it settles).
  //
  // Deliberately a pure opacity crossfade with no display:none/delayed-
  // removal step (unlike back-to-top's own original solo version): with
  // two independently-timed elements swapping in the same spot, a fast
  // "slam" scroll could catch them with neither element's fade-in having
  // started yet while the other's fade-out delay hadn't finished — briefly
  // neither visible. Both elements now stay in normal rendering at all
  // times; only their opacity (and pointer-events, so the hidden one can't
  // steal clicks) toggles, so they always continuously cross-fade rather
  // than discretely swap.
  function syncArrows(scrollY) {
    if (!pastHero && scrollY > arrowEnterThreshold) {
      pastHero = true;
    } else if (pastHero && scrollY < arrowExitThreshold) {
      pastHero = false;
    }

    if (backToTop) backToTop.classList.toggle('visible', pastHero);
    if (scrollDown) scrollDown.classList.toggle('visible', !pastHero);
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
    syncArrows(scrollY);
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

  if (scrollDownLink) {
    scrollDownLink.addEventListener('click', function (event) {
      event.preventDefault();

      // The mobile header stacks the brand and navigation. Measure a hidden
      // copy in its compact state so the next section lands directly below
      // the fixed header at every mobile width, without a hard-coded offset.
      const nextSection = hero ? hero.nextElementSibling : null;
      if (window.matchMedia('(max-width: 760px)').matches && nextSection) {
        const compactHeader = header.cloneNode(true);
        compactHeader.classList.add('is-scrolled');
        compactHeader.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;transition:none;';
        const compactInner = compactHeader.querySelector('.header-inner');
        if (compactInner) compactInner.style.transition = 'none';
        document.body.appendChild(compactHeader);
        const headerHeight = compactHeader.offsetHeight;
        compactHeader.remove();

        const sectionTop = nextSection.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ top: sectionTop - headerHeight, behavior: 'smooth' });
        return;
      }

      window.scrollTo({ top: heroHeight, behavior: 'smooth' });
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
  const csrfToken = document.querySelector('meta[name="csrf-token"]');

  const faqs = JSON.parse(root.querySelector('.chat-faq-data').textContent);
  const starterFaqs = selectStarterFaqs();
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  let hasOpenedBefore = false;
  let isBusy = false;
  let requestPending = false;
  let pendingReplies = 0;
  let replyQueue = Promise.resolve();

  function selectStarterFaqs() {
    const storageKey = 'dreamworldcirque.chat.starterFaqIds';
    let savedIds = [];
    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
      if (Array.isArray(saved)) savedIds = saved;
    } catch (error) {
      // The widget still works when browser storage is unavailable.
    }

    const selected = [...new Set(savedIds)]
      .map(function (id) { return faqs.find(function (faq) { return faq.id === id; }); })
      .filter(Boolean).slice(0, 2);
    const remaining = faqs.filter(function (faq) { return !selected.includes(faq); });
    while (selected.length < 2 && remaining.length) {
      selected.push(remaining.splice(Math.floor(Math.random() * remaining.length), 1)[0]);
    }

    try {
      sessionStorage.setItem(storageKey, JSON.stringify(selected.map(function (faq) { return faq.id; })));
    } catch (error) {
      // Keep the selected pair in memory for this page instead.
    }
    return selected;
  }

  function delay(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
  }

  // Reveals one word per tick at a fixed interval, rather than growing a
  // time/duration ratio that can jump several words at once on a tick --
  // that variable-size jump is what read as jittery. Longer replies get a
  // shorter per-word delay so they don't drag on forever.
  function revealWords(element) {
    if (reduceMotion.matches) return Promise.resolve();

    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    const words = [];
    while (walker.nextNode()) {
      const node = walker.currentNode;
      (node.nodeValue.match(/\S+\s*|\s+/g) || []).forEach(function (word) {
        words.push({ node: node, text: word });
      });
      node.nodeValue = '';
    }
    if (!words.length) return Promise.resolve();

    element.setAttribute('aria-busy', 'true');
    const wordDelay = Math.max(18, Math.min(55, 3500 / words.length));
    let revealed = 0;
    return new Promise(function (resolve) {
      const timer = setInterval(function () {
        const word = words[revealed++];
        word.node.nodeValue += word.text;
        messages.scrollTop = messages.scrollHeight;
        if (revealed === words.length) {
          clearInterval(timer);
          element.removeAttribute('aria-busy');
          resolve();
        }
      }, wordDelay);
    });
  }

  // Multi-paragraph answers reveal one block at a time (full paragraph typed
  // out, brief pause, next paragraph starts) instead of typing continuously
  // straight through paragraph breaks.
  function revealMessage(element) {
    const blocks = Array.from(element.children).filter(function (child) {
      return child.textContent.trim().length > 0;
    });
    if (blocks.length <= 1) return revealWords(element);

    blocks.forEach(function (block) { block.style.display = 'none'; });

    return blocks.reduce(function (promise, block, index) {
      return promise.then(function () {
        block.style.display = '';
        return revealWords(block);
      }).then(function () {
        if (index < blocks.length - 1) return delay(220);
      });
    }, Promise.resolve());
  }

  function addMessage(content, isUser, isHtml) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chat-message ' + (isUser ? 'chat-user-message' : 'chat-bot-message chat-answer');

    if (isHtml) {
      // FAQ answers are trusted, admin-authored RichEditor HTML -- the same
      // trust level the FAQ page itself renders with {!! $item->answer !!}.
      messageDiv.innerHTML = content;
    } else {
      messageDiv.textContent = content;
    }

    if (isUser) {
      messages.appendChild(messageDiv);
      messages.scrollTop = messages.scrollHeight;
      return Promise.resolve();
    }

    pendingReplies++;
    syncBusy();
    replyQueue = replyQueue.then(function () {
      messages.appendChild(messageDiv);
      messages.scrollTop = messages.scrollHeight;
      return revealMessage(messageDiv);
    }).finally(function () {
      pendingReplies--;
      syncBusy();
    });
    return replyQueue;
  }

  function addTypingIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'chat-message chat-bot-message chat-typing';
    indicator.innerHTML = '<span></span><span></span><span></span>';
    messages.appendChild(indicator);
    messages.scrollTop = messages.scrollHeight;

    return indicator;
  }

  function setBusy(busy) {
    requestPending = busy;
    syncBusy();
  }

  function syncBusy() {
    isBusy = requestPending || pendingReplies > 0;
    input.disabled = isBusy;
    sendButton.disabled = isBusy;
    root.querySelectorAll('.chat-chip, .chat-option').forEach(function (button) {
      button.disabled = isBusy;
    });
  }

  // Chip/option answers already have the content on hand (no network round
  // trip), but still pause on a "thinking" indicator first so they don't
  // feel out of step with the answers that do go through /chat/ask.
  function respondAfterThinking(question, answer) {
    setBusy(true);
    const typingIndicator = addTypingIndicator();
    return delay(500).then(function () {
      typingIndicator.remove();
      return addMessage(answer, false, true);
    }).then(function () {
      renderConversionCta(question);
    }).finally(function () {
      setBusy(false);
    });
  }

  function renderChips() {
    if (!starterFaqs.length) return;
    const chipsRow = document.createElement('div');
    chipsRow.className = 'chat-chips';

    starterFaqs.forEach(function (faq) {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'chat-chip';
      chip.textContent = faq.question;
      chip.addEventListener('click', function () {
        if (isBusy) return;
        chipsRow.remove();
        addMessage(faq.question, true);
        respondAfterThinking(faq.question, faq.answer);
      });
      chipsRow.appendChild(chip);
    });

    messages.appendChild(chipsRow);
    messages.scrollTop = messages.scrollHeight;
  }

  function renderOptions(optionsBlock, matches) {
    matches.forEach(function (match) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'chat-option';
      button.textContent = match.question;
      button.addEventListener('click', function () {
        if (isBusy) return;
        addMessage(match.question, true);
        optionsBlock.remove();
        respondAfterThinking(match.question, match.answer);
      });
      optionsBlock.appendChild(button);
    });
  }

  // The widget is designed for a single-turn Q&A, not an open-ended
  // conversation -- every answered FAQ (chip, option, or confident match)
  // ends with a direct path onward, rather than leaving the visitor to find
  // the FAQ page or contact form on their own.
  function renderConversionCta(question) {
    const cta = document.createElement('div');
    cta.className = 'chat-options chat-cta-row';

    const faqLink = document.createElement('a');
    faqLink.href = '/faq/';
    faqLink.className = 'chat-option';
    faqLink.textContent = 'View the FAQs';
    cta.appendChild(faqLink);

    const quoteButton = document.createElement('button');
    quoteButton.type = 'button';
    quoteButton.className = 'chat-option chat-cta-option';
    quoteButton.textContent = 'Get a Quote';
    quoteButton.addEventListener('click', function () {
      if (isBusy) return;
      cta.remove();
      renderLeadForm(question);
    });
    cta.appendChild(quoteButton);

    messages.appendChild(cta);
    messages.scrollTop = messages.scrollHeight;
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
      },
      body: JSON.stringify(body),
    });
  }

  function renderLeadForm(question) {
    const form = document.createElement('form');
    form.className = 'chat-lead-form';
    form.innerHTML =
      '<input type="text" class="chat-lead-field" name="name" placeholder="Your name" required />' +
      '<input type="email" class="chat-lead-field" name="email" placeholder="Your email" required />' +
      '<input type="tel" class="chat-lead-field" name="phone" placeholder="Your phone (optional)" />' +
      // Hidden from real visitors via CSS (see .chat-lead-form input[name="website"]
      // in styles.css) -- a bot filling every field it sees will fill this
      // one too, which is exactly what marks it as a bot server-side.
      '<input type="text" class="chat-lead-honeypot" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" />' +
      '<button type="submit" class="chat-lead-submit">Send to the team</button>' +
      '<p class="chat-field-error" hidden></p>';

    const errorEl = form.querySelector('.chat-field-error');
    const submitButton = form.querySelector('.chat-lead-submit');

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      errorEl.hidden = true;
      submitButton.disabled = true;

      postJson('/chat/lead', {
        name: form.name.value,
        email: form.email.value,
        phone: form.phone.value,
        question: question,
        website: form.website.value,
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            const firstError = result.data.errors && Object.values(result.data.errors)[0];
            errorEl.textContent = (firstError && firstError[0]) || 'Something went wrong — please try again.';
            errorEl.hidden = false;
            submitButton.disabled = false;
            return;
          }

          form.remove();
          addMessage(result.data.message, false);
        })
        .catch(function () {
          errorEl.textContent = 'Something went wrong — please try again.';
          errorEl.hidden = false;
          submitButton.disabled = false;
        });
    });

    messages.appendChild(form);
    messages.scrollTop = messages.scrollHeight;
  }

  function renderFallback(question) {
    return addMessage("I can help with performances, booking, and event requirements. Try asking about one of those, or connect with our team for more help.", false)
      .then(function () {
        const options = document.createElement('div');
        options.className = 'chat-options';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'chat-option';
        button.textContent = 'Connect me with the team';
        button.addEventListener('click', function () {
          if (isBusy) return;
          options.remove();
          renderLeadForm(question);
        });
        options.appendChild(button);
        messages.appendChild(options);
        messages.scrollTop = messages.scrollHeight;
      });
  }

  function handleResponse(question, data) {
    if (data.outcome === 'greeting' || data.outcome === 'farewell') {
      return addMessage(data.reply, false);
    }

    if (data.outcome === 'confident' && data.matches.length > 0) {
      return addMessage(data.matches[0].answer, false, true).then(function () {
        renderConversionCta(question);
      });
    }

    if (data.outcome === 'ambiguous' && data.matches.length > 0) {
      const optionsBlock = document.createElement('div');
      optionsBlock.className = 'chat-options';
      return addMessage("Here's what I can help with:", false).then(function () {
        messages.appendChild(optionsBlock);
        renderOptions(optionsBlock, data.matches);
        messages.scrollTop = messages.scrollHeight;
      });
    }

    return renderFallback(question);
  }

  function askQuestion(question) {
    if (!question.trim() || isBusy || !csrfToken) return;

    root.querySelectorAll('.chat-chips, .chat-options').forEach(function (element) { element.remove(); });
    addMessage(question, true);
    setBusy(true);

    const typingIndicator = addTypingIndicator();

    // The FAQ match itself resolves near-instantly -- pairing it with a
    // fixed minimum delay keeps the "thinking" indicator from just flashing
    // by, which read as too abrupt.
    Promise.all([
      postJson('/chat/ask', { question: question }).then(function (response) {
        if (!response.ok) throw new Error('chat request failed');
        return response.json();
      }),
      delay(500),
    ])
      .then(function (results) {
        const data = results[0];
        typingIndicator.remove();
        return handleResponse(question, data);
      })
      .catch(function () {
        typingIndicator.remove();
        return addMessage("Something went wrong on our end — please try again, or use the contact form below.", false);
      })
      .finally(function () {
        setBusy(false);
      });
  }

  function sendMessage(message) {
    const question = message.trim();
    if (!question || isBusy) return;

    input.value = '';
    askQuestion(question);
  }

  closeButton.addEventListener('click', function () {
    root.classList.remove('is-open');
  });

  reopenButton.addEventListener('click', function () {
    root.classList.add('is-open');

    if (!hasOpenedBefore) {
      hasOpenedBefore = true;
      addMessage("Hi! I can help with questions about Dream World Cirque. :)", false)
        .then(renderChips);
    }
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

  function clearPendingEnd(answer) {
    if (answer._pendingEnd) {
      answer._pendingEnd();
    }
  }

  function afterHeightTransition(answer, onFinish) {
    return new Promise(function (resolve) {
      let timeout;

      function cleanup() {
        answer.removeEventListener('transitionend', onTransitionEnd);
        window.clearTimeout(timeout);
        answer._pendingEnd = null;
      }

      function cancel() {
        cleanup();
        resolve();
      }

      function finish() {
        cleanup();
        onFinish();
        resolve();
      }

      function onTransitionEnd(event) {
        if (event.target === answer && event.propertyName === 'height') finish();
      }

      answer._pendingEnd = cancel;
      const duration = (parseFloat(window.getComputedStyle(answer).transitionDuration) || 0) * 1000;

      if (!duration) {
        finish();
        return;
      }

      answer.addEventListener('transitionend', onTransitionEnd);
      timeout = window.setTimeout(finish, duration + 50);
    });
  }

  function closeItem(item) {
    const answer = item.querySelector('.faq-answer');
    clearPendingEnd(answer);
    answer.style.height = answer.getBoundingClientRect().height + 'px';
    answer.getBoundingClientRect(); // force reflow so the next line animates
    answer.style.height = '0px';

    return afterHeightTransition(answer, function () {
      item.open = false;
      answer.style.height = '';
    });
  }

  function openItem(item) {
    const answer = item.querySelector('.faq-answer');
    clearPendingEnd(answer);
    item.open = true;
    const targetHeight = answer.scrollHeight;
    answer.style.height = '0px';
    answer.getBoundingClientRect(); // force reflow so the next line animates
    answer.style.height = targetHeight + 'px';

    afterHeightTransition(answer, function () {
      // Leave it at "auto" (not "") so it stays open and reflows with
      // content/viewport changes, instead of snapping to the CSS default
      // of height:0 and immediately animating shut again.
      answer.style.height = 'auto';
    });
  }

  // Scroll token below: incremented on every open click, so a deferred
  // scroll from a rapid earlier click can tell it's no longer current and
  // skip instead of yanking the page back to a question the user has
  // already moved on from.
  let scrollToken = 0;

  items.forEach(function (item) {
    const summary = item.querySelector('summary');

    summary.addEventListener('click', function (event) {
      event.preventDefault();

      if (item.open) {
        closeItem(item);
        return;
      }

      // Only one answer open at a time: close any other item that's
      // currently open before opening this one.
      const closing = [];
      items.forEach(function (other) {
        if (other !== item && other.open) {
          closing.push(closeItem(other));
        }
      });

      openItem(item);

      // Agency-style FAQ snap: the opened question scrolls to the top of
      // the viewport so its answer reads right below the header, rather
      // than the accordion just growing wherever the click happened to
      // land. If another item is closing at the same time, its shrinking
      // height pulls this item upward over that same span, so scrolling
      // immediately would target a now-stale position -- and running a
      // second corrective scroll afterward doesn't work either, since it
      // fires while the first (already wrong) smooth scroll is still
      // animating toward its stale target and gets dragged along with it.
      // Waiting for any closing item(s) to finish first and scrolling
      // exactly once, against the settled layout, avoids the race outright.
      const thisToken = ++scrollToken;
      Promise.all(closing).then(function () {
        if (thisToken !== scrollToken) return;
        item.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  });
})();

(function () {
  document.querySelectorAll('[data-contact-reveal]').forEach(function (trigger) {
    const panel = document.getElementById(trigger.getAttribute('aria-controls'));
    if (!panel) return;

    trigger.addEventListener('click', function (event) {
      event.preventDefault();
      panel.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');

      const firstField = panel.querySelector('input[name="name"]:not(:disabled)');
      if (firstField) firstField.focus({ preventScroll: true });
      panel.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
        block: 'start',
      });
    });
  });
})();

(function () {
  const forms = document.querySelectorAll('.contact-form');
  if (!forms.length) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]');
  if (!csrfToken) return;

  forms.forEach(function (form) {
    const submitButton = form.querySelector('.contact-form-submit');
    const statusEl = form.querySelector('.contact-form-status');
    const errorEls = form.querySelectorAll('.contact-form-error');

    function clearErrors() {
      errorEls.forEach(function (el) {
        el.hidden = true;
        el.textContent = '';
      });
      statusEl.hidden = true;
    }

    function showFieldErrors(errors) {
      Object.keys(errors).forEach(function (field) {
        const el = form.querySelector('.contact-form-error[data-field="' + field + '"]');
        if (el) {
          el.textContent = errors[field][0];
          el.hidden = false;
        }
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      clearErrors();
      submitButton.disabled = true;

      const interests = Array.from(form.querySelectorAll('input[name="interests[]"]:checked')).map(function (checkbox) {
        return checkbox.value;
      });

      fetch(form.action, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
        },
        body: JSON.stringify({
          name: form.name.value,
          email: form.email.value,
          phone: form.phone.value,
          message: form.message.value,
          interests: interests,
          form_type: form.form_type.value,
          website: form.website.value,
        }),
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, status: response.status, data: data };
          });
        })
        .then(function (result) {
          if (result.status === 422) {
            showFieldErrors(result.data.errors || {});
            submitButton.disabled = false;
            return;
          }

          if (!result.ok) {
            statusEl.textContent = 'Something went wrong - please try again.';
            statusEl.hidden = false;
            submitButton.disabled = false;
            return;
          }

          // Leaves the fields in place (rather than clearing/hiding the
          // form) so a visitor can see exactly what they sent -- only the
          // submit button and inputs lock, with the confirmation appended
          // above them.
          statusEl.textContent = result.data.message;
          statusEl.hidden = false;
          Array.from(form.elements).forEach(function (element) {
            element.disabled = true;
          });
        })
        .catch(function () {
          statusEl.textContent = 'Something went wrong - please try again.';
          statusEl.hidden = false;
          submitButton.disabled = false;
        });
    });
  });
})();
