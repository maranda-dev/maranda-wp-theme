document.querySelectorAll('[data-maranda-contact] button[type="submit"]').forEach(button => { button.disabled = false; });
document.addEventListener('submit', async event => {
  const form = event.target.closest('[data-maranda-contact]');
  if (!form) return;
  event.preventDefault();
  if (!form.reportValidity()) return;
  const button = form.querySelector('button[type="submit"]');
  if (button.disabled) return;
  const status = form.querySelector('[role="status"]');
  button.disabled = true;
  status.textContent = 'Envoi en cours…';
  try {
    const response = await fetch(form.dataset.endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(Object.fromEntries(new FormData(form)))
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || response.status !== 201) {
      status.textContent = result?.message || 'Le message n’a pas pu être envoyé. Veuillez réessayer plus tard.';
      return;
    }
    status.textContent = result.message;
    form.querySelector('[name="name"]').value = '';
    form.querySelector('[name="email"]').value = '';
    form.querySelector('[name="message"]').value = '';
  } catch {
    status.textContent = 'La connexion a été interrompue. Veuillez réessayer plus tard.';
  } finally {
    button.disabled = false;
  }
});
