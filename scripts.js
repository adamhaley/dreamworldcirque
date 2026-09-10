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

(function () {
  const root = document.querySelector('.back-to-top-link');
  if (!root) return;

  const threshold = 140;
  const link = root.querySelector('a');
  let hideTimer;

  function syncBackToTop() {
    if (window.scrollY > threshold) {
      root.classList.add('in');
      clearTimeout(hideTimer);
      requestAnimationFrame(() => root.classList.add('visible'));
      return;
    }

    root.classList.remove('visible');
    hideTimer = window.setTimeout(() => root.classList.remove('in'), 180);
  }

  link.addEventListener('click', function (event) {
    event.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  window.addEventListener('scroll', syncBackToTop, { passive: true });
  syncBackToTop();
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