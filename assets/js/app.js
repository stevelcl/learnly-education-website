document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');

  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      nav.classList.toggle('open');
    });
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
