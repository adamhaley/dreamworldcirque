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

  const STARTER_CHIPS = [
    'How much does it cost?',
    'Do you travel outside LA and Vegas?',
    'How far in advance should we book?',
    'Do you provide insurance?',
  ];

  let hasOpenedBefore = false;
  let isBusy = false;

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

    messages.appendChild(messageDiv);
    messages.scrollTop = messages.scrollHeight;

    return messageDiv;
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
    isBusy = busy;
    input.disabled = busy;
    sendButton.disabled = busy;
  }

  function renderChips() {
    const chipsRow = document.createElement('div');
    chipsRow.className = 'chat-chips';

    STARTER_CHIPS.forEach(function (question) {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'chat-chip';
      chip.textContent = question;
      chip.addEventListener('click', function () {
        chipsRow.remove();
        askQuestion(question);
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
        addMessage(match.question, true);
        optionsBlock.remove();
        addMessage(match.answer, false, true);
      });
      optionsBlock.appendChild(button);
    });
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
    addMessage("I don't have an answer for that one — but I can pass it along to the team:", false);
    renderLeadForm(question);
  }

  function handleResponse(question, data) {
    if (data.outcome === 'confident' && data.matches.length > 0) {
      addMessage(data.matches[0].answer, false, true);
      return;
    }

    if (data.outcome === 'ambiguous' && data.matches.length > 0) {
      const optionsBlock = document.createElement('div');
      optionsBlock.className = 'chat-options';
      addMessage("I'm not totally sure — did you mean:", false);
      messages.appendChild(optionsBlock);
      renderOptions(optionsBlock, data.matches);
      messages.scrollTop = messages.scrollHeight;
      return;
    }

    renderFallback(question);
  }

  function askQuestion(question) {
    if (!question.trim() || isBusy || !csrfToken) return;

    addMessage(question, true);
    setBusy(true);

    const typingIndicator = addTypingIndicator();

    postJson('/chat/ask', { question: question })
      .then(function (response) {
        if (!response.ok) throw new Error('chat request failed');
        return response.json();
      })
      .then(function (data) {
        typingIndicator.remove();
        handleResponse(question, data);
      })
      .catch(function () {
        typingIndicator.remove();
        addMessage("Something went wrong on our end — please try again, or use the contact form below.", false);
      })
      .finally(function () {
        setBusy(false);
      });
  }

  function sendMessage(message) {
    const question = message.trim();
    if (!question) return;

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
      addMessage("Hi! Ask me anything about Dream World Cirque, or pick a question below.", false);
      renderChips();
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

  // Auto-closing another item (below) can start a new transition on an
  // answer while its *previous* open/close transitionend handler is still
  // attached and pending — both fire on whichever transition completes
  // first, and the stale one (e.g. the original "open" handler setting
  // height back to auto) can win the race, leaving the item stuck open.
  // Tracking and explicitly removing any pending handler before starting
  // a new transition guarantees only the latest one ever runs.
  function clearPendingEnd(answer) {
    if (answer._pendingEnd) {
      answer.removeEventListener('transitionend', answer._pendingEnd);
      answer._pendingEnd = null;
    }
  }

  function closeItem(item) {
    const answer = item.querySelector('.faq-answer');
    clearPendingEnd(answer);
    answer.style.height = answer.scrollHeight + 'px';
    answer.getBoundingClientRect(); // force reflow so the next line animates
    answer.style.height = '0px';
    const onEnd = function () {
      item.open = false;
      answer.style.height = '';
      answer.removeEventListener('transitionend', onEnd);
      answer._pendingEnd = null;
    };
    answer._pendingEnd = onEnd;
    answer.addEventListener('transitionend', onEnd, { once: true });
  }

  function openItem(item) {
    const answer = item.querySelector('.faq-answer');
    clearPendingEnd(answer);
    item.open = true;
    const targetHeight = answer.scrollHeight;
    answer.style.height = '0px';
    answer.getBoundingClientRect(); // force reflow so the next line animates
    answer.style.height = targetHeight + 'px';
    const onEnd = function () {
      // Leave it at "auto" (not "") so it stays open and reflows with
      // content/viewport changes, instead of snapping to the CSS default
      // of height:0 and immediately animating shut again.
      answer.style.height = 'auto';
      answer.removeEventListener('transitionend', onEnd);
      answer._pendingEnd = null;
    };
    answer._pendingEnd = onEnd;
    answer.addEventListener('transitionend', onEnd, { once: true });
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
      const others = [];
      items.forEach(function (other) {
        if (other !== item && other.open) {
          others.push(other);
          closeItem(other);
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
      Promise.all(others.map(function (other) {
        return new Promise(function (resolve) {
          other.querySelector('.faq-answer').addEventListener('transitionend', resolve, { once: true });
        });
      })).then(function () {
        if (thisToken !== scrollToken) return;
        item.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
            statusEl.textContent = 'Something went wrong — please try again.';
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
          statusEl.textContent = 'Something went wrong — please try again.';
          statusEl.hidden = false;
          submitButton.disabled = false;
        });
    });
  });
})();
