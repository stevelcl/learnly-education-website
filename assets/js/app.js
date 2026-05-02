document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  const resourceSelect = document.querySelector('[data-resource-select]');
  const resourcePanels = document.querySelectorAll('[data-resource-fields]');
  const coverFileInput = document.querySelector('[data-cover-file-input]');
  const coverPreview = document.querySelector('[data-cover-preview]');

  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      nav.classList.toggle('open');
    });
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
});
