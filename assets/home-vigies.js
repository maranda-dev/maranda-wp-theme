document.querySelectorAll('[data-home-vigies]').forEach(section => {
  const slides = [...section.querySelectorAll('[data-vigie-slide]')];
  if (slides.length < 2) return;
  const current = section.querySelector('[data-vigie-current]');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let index = 0;
  let timer;
  let transitioning = false;

  const schedule = () => {
    window.clearTimeout(timer);
    if (!reduceMotion.matches && !document.hidden && !section.matches(':hover, :focus-within')) {
      timer = window.setTimeout(() => show(index + 1, 1), 6000);
    }
  };

  const show = (next, direction = next > index ? 1 : -1) => {
    if (transitioning) return;
    const nextIndex = (next + slides.length) % slides.length;
    if (nextIndex === index) return;
    transitioning = true;
    const outgoing = slides[index];
    const incoming = slides[nextIndex];
    const suffix = direction > 0 ? 'forward' : 'backward';
    incoming.hidden = false;
    incoming.classList.add(`is-entering-${suffix}`);
    outgoing.classList.add(`is-leaving-${suffix}`);
    window.setTimeout(() => {
      outgoing.hidden = true;
      outgoing.className = 'rr-vigie-slide';
      incoming.className = 'rr-vigie-slide is-current';
      index = nextIndex;
      transitioning = false;
      schedule();
    }, reduceMotion.matches ? 20 : 1100);
    if (current) current.textContent = String(nextIndex + 1).padStart(2, '0');
  };

  section.querySelector('[data-vigie-prev]')?.addEventListener('click', () => { window.clearTimeout(timer); show(index - 1, -1); });
  section.querySelector('[data-vigie-next]')?.addEventListener('click', () => { window.clearTimeout(timer); show(index + 1, 1); });
  section.addEventListener('mouseenter', () => window.clearTimeout(timer));
  section.addEventListener('mouseleave', schedule);
  section.addEventListener('focusin', () => window.clearTimeout(timer));
  section.addEventListener('focusout', schedule);
  document.addEventListener('visibilitychange', schedule);
  reduceMotion.addEventListener?.('change', schedule);
  schedule();
});
