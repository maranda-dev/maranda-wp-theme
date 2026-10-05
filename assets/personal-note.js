(() => {
  const note = document.getElementById('mm-personal-note');
  if (!note || typeof note.show !== 'function') return;
  const storageKey = `mm-announcement-${note.dataset.revision}-dismissed`;
  if (note.dataset.preview !== '1') {
    try { if (sessionStorage.getItem(storageKey)) return; } catch {}
  }
  const positionNote = () => {
    const header = document.querySelector('.rr-header, .header, .mm-header');
    const adminBar = document.getElementById('wpadminbar');
    const bottom = Math.max(0, header?.getBoundingClientRect().bottom || 0, adminBar?.getBoundingClientRect().bottom || 0);
    note.style.setProperty('--mm-note-top', `${Math.ceil(bottom + 16)}px`);
  };
  positionNote();
  window.addEventListener('resize', positionNote);
  window.addEventListener('scroll', positionNote, {passive: true});
  if (typeof ResizeObserver !== 'undefined') {
    const observer = new ResizeObserver(positionNote);
    for (const element of document.querySelectorAll('.rr-header, .header, .mm-header, #wpadminbar')) observer.observe(element);
  }
  const previousFocus = document.activeElement;
  note.querySelectorAll('[data-note-close]').forEach(button => {
    button.addEventListener('click', () => note.close());
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && note.open) {
      event.preventDefault();
      note.close();
    }
  });
  note.addEventListener('close', () => {
    if (note.dataset.preview !== '1') {
      try { sessionStorage.setItem(storageKey, '1'); } catch {}
    }
    if (note.contains(document.activeElement) && previousFocus instanceof HTMLElement) previousFocus.focus();
  });
  // Non-modal: no backdrop, focus trap, inert page or scroll lock.
  note.show();
  if (previousFocus instanceof HTMLElement) previousFocus.focus({preventScroll: true});
})();
