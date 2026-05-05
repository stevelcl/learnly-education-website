document.addEventListener('DOMContentLoaded', () => {
  document.documentElement.classList.add('js');
  const header = document.querySelector('[data-site-header]');
  const navToggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  const searchToggle = document.querySelector('[data-search-toggle]');
  const searchForm = document.querySelector('[data-search-form]');
  const searchInput = document.querySelector('#site-search-input');
  const resourceSelect = document.querySelector('[data-resource-select]');
  const resourcePanels = document.querySelectorAll('[data-resource-fields]');
  const coverFileInput = document.querySelector('[data-cover-file-input]');
  const coverPreview = document.querySelector('[data-cover-preview]');
  const revealItems = document.querySelectorAll('[data-reveal]');
  const learningShell = document.querySelector('[data-learning-shell]');
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const syncHeaderState = () => {
    if (!(header instanceof HTMLElement)) {
      return;
    }

    header.classList.toggle('is-scrolled', window.scrollY > 24);
  };

  syncHeaderState();
  window.addEventListener('scroll', syncHeaderState, { passive: true });

  if (navToggle && nav) {
    navToggle.setAttribute('aria-expanded', 'false');

    navToggle.addEventListener('click', () => {
      const nextState = !nav.classList.contains('open');
      nav.classList.toggle('open', nextState);
      navToggle.setAttribute('aria-expanded', nextState ? 'true' : 'false');

      if (nextState && searchForm instanceof HTMLFormElement && window.innerWidth <= 760) {
        searchForm.classList.remove('open');
        if (searchToggle instanceof HTMLButtonElement) {
          searchToggle.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  if (searchToggle instanceof HTMLButtonElement && searchForm instanceof HTMLFormElement) {
    const closeSearch = () => {
      searchForm.classList.remove('open');
      searchToggle.setAttribute('aria-expanded', 'false');
    };

    const openSearch = () => {
      searchForm.classList.add('open');
      searchToggle.setAttribute('aria-expanded', 'true');
      if (searchInput instanceof HTMLInputElement && window.innerWidth <= 760) {
        window.setTimeout(() => searchInput.focus(), 120);
      }
    };

    searchToggle.addEventListener('click', () => {
      const isOpen = searchForm.classList.contains('open');
      if (isOpen) {
        closeSearch();
      } else {
        if (nav instanceof HTMLElement && nav.classList.contains('open') && window.innerWidth <= 760) {
          nav.classList.remove('open');
          if (navToggle instanceof HTMLButtonElement) {
            navToggle.setAttribute('aria-expanded', 'false');
          }
        }
        openSearch();
      }
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 760) {
        searchForm.classList.remove('open');
        searchToggle.setAttribute('aria-expanded', 'false');
      }

      if (window.innerWidth > 980 && nav instanceof HTMLElement && nav.classList.contains('open')) {
        nav.classList.remove('open');
        if (navToggle instanceof HTMLButtonElement) {
          navToggle.setAttribute('aria-expanded', 'false');
        }
      }
    });

    document.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Node)) {
        return;
      }

      if (!searchForm.contains(target) && !searchToggle.contains(target) && window.innerWidth <= 760) {
        closeSearch();
      }

      if (
        nav instanceof HTMLElement &&
        navToggle instanceof HTMLButtonElement &&
        window.innerWidth <= 980 &&
        nav.classList.contains('open') &&
        !nav.contains(target) &&
        !navToggle.contains(target)
      ) {
        nav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  if (revealItems.length > 0) {
    revealItems.forEach((item) => {
      if (!(item instanceof HTMLElement)) {
        return;
      }

      const delay = Number.parseInt(item.dataset.revealDelay || '0', 10);
      item.style.setProperty('--reveal-delay', Number.isNaN(delay) ? '0' : String(delay));
    });

    if (prefersReducedMotion) {
      revealItems.forEach((item) => {
        if (item instanceof HTMLElement) {
          item.classList.add('is-visible');
        }
      });
    } else if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          if (entry.target instanceof HTMLElement) {
            entry.target.classList.add('is-visible');
          }
          currentObserver.unobserve(entry.target);
        });
      }, {
        rootMargin: '0px 0px -10% 0px',
        threshold: 0.14
      });

      revealItems.forEach((item) => {
        observer.observe(item);
      });
    } else {
      revealItems.forEach((item) => {
        if (item instanceof HTMLElement) {
          item.classList.add('is-visible');
        }
      });
    }
  }

  if (resourceSelect && resourcePanels.length > 0) {
    const syncResourcePanels = () => {
      resourcePanels.forEach((panel) => {
        if (!(panel instanceof HTMLElement)) {
          return;
        }

        const matches = panel.dataset.resourceFields === resourceSelect.value;
        panel.hidden = !matches;

        panel.querySelectorAll('input, textarea, select').forEach((field) => {
          if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
          }

          if (matches) {
            field.removeAttribute('disabled');
          } else {
            field.setAttribute('disabled', 'disabled');
          }
        });
      });
    };

    syncResourcePanels();
    resourceSelect.addEventListener('change', syncResourcePanels);
  }

  if (coverFileInput instanceof HTMLInputElement && coverPreview instanceof HTMLImageElement) {
    const syncCoverPreview = () => {
      const file = coverFileInput.files && coverFileInput.files[0];
      if (!file) {
        if ((coverPreview.dataset.hasExisting || '') !== '1') {
          coverPreview.hidden = true;
          coverPreview.src = coverPreview.dataset.placeholderSrc || '';
        }
        return;
      }

      coverPreview.hidden = false;
      coverPreview.src = URL.createObjectURL(file);
      coverPreview.onload = () => {
        URL.revokeObjectURL(coverPreview.src);
      };
    };

    syncCoverPreview();
    coverFileInput.addEventListener('change', syncCoverPreview);
  }

  if (learningShell instanceof HTMLElement && learningShell.dataset.adminPreview !== '1') {
    const progressEndpoint = learningShell.dataset.progressEndpoint || '';
    const csrfToken = learningShell.dataset.csrfToken || '';
    const courseId = Number.parseInt(learningShell.dataset.courseId || '0', 10);
    const currentStep = Number.parseInt(learningShell.dataset.currentStep || '0', 10);
    const currentItem = learningShell.querySelector('[data-learning-item]');
    const noteTrigger = learningShell.querySelector('[data-complete-trigger]');
    const completionHint = learningShell.querySelector('[data-completion-hint]');
    const outlineItems = Array.from(learningShell.querySelectorAll('[data-outline-item]'));
    const nextDisabled = learningShell.querySelector('[data-next-lesson-disabled]');
    const progressPercentNode = learningShell.querySelector('[data-progress-percent]');
    const progressSummaryNode = learningShell.querySelector('[data-progress-summary]');
    const progressBar = learningShell.querySelector('[data-progress-bar]');
    let completionPending = false;

    const markUiComplete = (response) => {
      if (!(currentItem instanceof HTMLElement)) {
        return;
      }

      currentItem.dataset.itemComplete = '1';
      learningShell.dataset.currentComplete = '1';

      if (completionHint instanceof HTMLElement) {
        completionHint.textContent = 'Lesson completed. The next lesson is now unlocked.';
        completionHint.classList.add('done');
      }

      if (progressPercentNode instanceof HTMLElement) {
        progressPercentNode.textContent = `${response.progress_percent}%`;
      }
      if (progressSummaryNode instanceof HTMLElement) {
        progressSummaryNode.textContent = `${response.completed_items} of ${response.total_items} completed`;
      }
      if (progressBar instanceof HTMLElement) {
        progressBar.style.width = `${response.progress_percent}%`;
      }

      const currentOutline = outlineItems.find((item) => Number.parseInt(item.dataset.stepIndex || '-1', 10) === currentStep);
      if (currentOutline instanceof HTMLElement) {
        currentOutline.classList.add('complete');
        const status = currentOutline.querySelector('.learning-outline-status');
        if (status instanceof HTMLElement) {
          status.textContent = 'Done';
        }
      }

      const nextIndex = currentStep + 1;
      const nextOutline = outlineItems.find((item) => Number.parseInt(item.dataset.stepIndex || '-1', 10) === nextIndex);
      if (nextOutline instanceof HTMLElement && nextOutline.classList.contains('locked')) {
        nextOutline.classList.remove('locked');
        const nextUrl = nextOutline.dataset.stepUrl || '';
        if (nextUrl !== '') {
          const unlocked = document.createElement('a');
          unlocked.className = 'learning-outline-item';
          unlocked.href = nextUrl;
          unlocked.dataset.outlineItem = '1';
          unlocked.dataset.stepIndex = String(nextIndex);
          unlocked.dataset.stepUrl = nextUrl;
          unlocked.innerHTML = nextOutline.innerHTML;
          nextOutline.replaceWith(unlocked);
        }
      }

      if (nextDisabled instanceof HTMLElement) {
        const nextUrl = `${learningShell.dataset.baseUrl}?step=${nextIndex}`;
        const nextButton = document.createElement('a');
        nextButton.className = 'button';
        nextButton.href = nextUrl;
        nextButton.textContent = 'Next Lesson';
        nextButton.setAttribute('data-next-lesson', '1');
        nextDisabled.replaceWith(nextButton);
      }

      if (response.progress_percent >= 100) {
        window.location.assign(`${learningShell.dataset.baseUrl}?step=${currentStep}#completion-panel`);
      }
    };

    const completeCurrentResource = async () => {
      if (completionPending || !(currentItem instanceof HTMLElement)) {
        return;
      }
      if (currentItem.dataset.itemType !== 'resource' || currentItem.dataset.itemComplete === '1') {
        return;
      }

      const itemId = Number.parseInt(currentItem.dataset.itemId || '0', 10);
      if (progressEndpoint === '' || csrfToken === '' || courseId <= 0 || itemId <= 0) {
        return;
      }

      completionPending = true;

      const body = new URLSearchParams();
      body.set('course_id', String(courseId));
      body.set('item_type', 'resource');
      body.set('item_id', String(itemId));
      body.set('csrf_token', csrfToken);

      try {
        const response = await fetch(progressEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString()
        });

        const payload = await response.json();
        if (response.ok && payload.ok) {
          markUiComplete(payload);
        }
      } catch (error) {
        // Keep the interface usable if the request fails; the user can refresh and continue.
      } finally {
        completionPending = false;
      }
    };

    if (noteTrigger instanceof HTMLElement) {
      if ('IntersectionObserver' in window) {
        const noteObserver = new IntersectionObserver((entries, observer) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) {
              return;
            }

            completeCurrentResource();
            observer.disconnect();
          });
        }, {
          threshold: 0.8
        });

        noteObserver.observe(noteTrigger);
      } else {
        const onScrollComplete = () => {
          const rect = noteTrigger.getBoundingClientRect();
          if (rect.top <= window.innerHeight) {
            completeCurrentResource();
            window.removeEventListener('scroll', onScrollComplete);
          }
        };

        window.addEventListener('scroll', onScrollComplete, { passive: true });
        onScrollComplete();
      }
    }

    const videoIframe = learningShell.querySelector('[data-video-progress]');
    if (videoIframe instanceof HTMLIFrameElement) {
      const setupYouTubeTracking = () => {
        const initializePlayer = () => {
          if (!(window.YT && typeof window.YT.Player === 'function')) {
            return;
          }

          const player = new window.YT.Player(videoIframe);
          let watchedEnough = false;

          const checkProgress = () => {
            if (watchedEnough) {
              return;
            }

            const duration = player.getDuration();
            const current = player.getCurrentTime();
            if (duration > 0 && current / duration >= 0.9) {
              watchedEnough = true;
              completeCurrentResource();
            }
          };

          player.addEventListener('onStateChange', (event) => {
            if (event.data === window.YT.PlayerState.ENDED) {
              watchedEnough = true;
              completeCurrentResource();
            }
          });

          window.setInterval(checkProgress, 1500);
        };

        if (window.YT && typeof window.YT.Player === 'function') {
          initializePlayer();
          return;
        }

        const previousReady = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
          if (typeof previousReady === 'function') {
            previousReady();
          }
          initializePlayer();
        };

        if (!document.querySelector('script[src="https://www.youtube.com/iframe_api"]')) {
          const script = document.createElement('script');
          script.src = 'https://www.youtube.com/iframe_api';
          document.head.appendChild(script);
        }
      };

      setupYouTubeTracking();
    }
  }

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    const confirmButton = target.closest('[data-confirm]');
    if (confirmButton instanceof HTMLElement) {
      const message = confirmButton.getAttribute('data-confirm') || '';
      if (message !== '' && !window.confirm(message)) {
        event.preventDefault();
        return;
      }
    }

    const toggle = target.closest('[data-password-toggle]');
    if (!(toggle instanceof HTMLElement)) {
      return;
    }

    event.preventDefault();
    const wrapper = toggle.closest('.password-field');
    if (!(wrapper instanceof HTMLElement)) {
      return;
    }

    const input = wrapper.querySelector('[data-password-input]');
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    const showPassword = input.type === 'password';
    input.type = showPassword ? 'text' : 'password';
    toggle.textContent = showPassword ? 'Hide' : 'Show';
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    if (searchForm instanceof HTMLFormElement && searchToggle instanceof HTMLButtonElement) {
      searchForm.classList.remove('open');
      searchToggle.setAttribute('aria-expanded', 'false');
    }

    if (nav instanceof HTMLElement && navToggle instanceof HTMLButtonElement) {
      nav.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    }
  });
});
