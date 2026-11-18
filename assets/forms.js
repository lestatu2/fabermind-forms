// Fabermind Forms: invia i moduli senza ricaricare la pagina e mostra gli errori accanto ai campi.
(() => {
  function collect(form) {
    const data = {};
    for (const [name, value] of new FormData(form)) {
      if (name.endsWith('[]')) (data[name.slice(0, -2)] ||= []).push(value);
      else data[name] = value;
    }
    return data;
  }

  function showErrors(form, errors) {
    let first = null;
    form.querySelectorAll('[data-fm-field]').forEach((box) => {
      const message = errors[box.dataset.fmField] || '';
      const text = box.querySelector('[data-fm-error]');
      const control = box.querySelector('input:not([type="checkbox"]), select, textarea');
      text.textContent = message;
      text.classList.toggle('hidden', !message);
      // Bordo rosso del componente DaisyUI giusto (le classi sono scritte per intero perche' Tailwind le trovi).
      const errorClass = { INPUT: 'input-error', SELECT: 'select-error', TEXTAREA: 'textarea-error' }[control?.tagName];
      if (errorClass) control.classList.toggle(errorClass, Boolean(message));
      control?.setAttribute('aria-invalid', String(Boolean(message)));
      if (message && !first) first = box.querySelector('input, select, textarea');
    });
    first?.focus();
  }

  function showMessage(form, text, kind) {
    const box = form.querySelector('[data-fm-message]');
    box.replaceChildren();
    if (!text) return;
    const alert = document.createElement('div');
    alert.className = `alert alert-soft text-base-content ${kind === 'ok' ? 'alert-success' : 'alert-error'}`;
    alert.textContent = text;
    box.append(alert);
  }

  // Quando si corregge un campo, il suo errore sparisce.
  document.addEventListener('input', (event) => {
    const box = event.target.closest?.('[data-fm-form] [data-fm-field]');
    const text = box?.querySelector('[data-fm-error]');
    if (!text || text.classList.contains('hidden')) return;
    text.classList.add('hidden');
    event.target.classList.remove('input-error', 'select-error', 'textarea-error');
    event.target.removeAttribute('aria-invalid');
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('[data-fm-form]');
    if (!form) return;
    event.preventDefault();

    const button = form.querySelector('[type="submit"]');
    const spinner = document.createElement('span');
    spinner.className = 'loading loading-spinner loading-sm';
    button.disabled = true;
    button.prepend(spinner);
    showMessage(form, '');

    try {
      const response = await fetch(form.dataset.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(collect(form)),
      });
      const result = await response.json();
      showErrors(form, result.errors || {});
      if (result.ok) {
        showMessage(form, result.message, 'ok');
        form.reset();
        if (result.redirect) {
          window.location.assign(result.redirect);
          return; // Il pulsante resta fermo mentre la pagina cambia.
        }
      } else {
        showMessage(form, result.message || form.dataset.error, 'error');
      }
    } catch {
      showMessage(form, form.dataset.error, 'error');
    }
    spinner.remove();
    button.disabled = false;
  });
})();
