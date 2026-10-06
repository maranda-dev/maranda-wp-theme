(() => {
  'use strict';
  const config = window.marandaLinkPreviews;
  if (!config || !config.images) return;
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  const site = new URL(config.siteUrl, window.location.href);
  const basePath = site.pathname.replace(/\/$/, '');
  const destinations = {
    '/inspekt': { key: 'inspekt', label: 'InspeKT · tableau de bord' },
    '/carte-des-releves': { key: 'carte', label: 'Carte · relevés terrain' },
    '/vigie-reseau': { key: 'vigies', label: 'Vigies · dossiers et suivis' },
  };
  const host = value => value.replace(/^www\./, '');
  const preview = document.createElement('figure');
  preview.className = 'mm-link-preview';
  preview.hidden = true;
  preview.setAttribute('aria-hidden', 'true');
  const image = document.createElement('img');
  image.alt = '';
  const caption = document.createElement('figcaption');
  preview.append(image, caption);
  document.body.append(preview);
  let active = null;
  function hide() { active = null; preview.hidden = true; }
  function destination(link) {
    if (!link || !link.closest('main') || link.closest('nav, header, footer, [data-inspekt-map]')) return null;
    const url = new URL(link.href, site);
    if (!['http:', 'https:'].includes(url.protocol) || host(url.hostname) !== host(site.hostname) || url.port !== site.port) return null;
    return destinations[url.pathname.replace(/\/$/, '').slice(basePath.length)] || null;
  }
  function position() {
    if (!active || preview.hidden) return;
    const rect = active.getBoundingClientRect();
    const gap = 12;
    const width = preview.offsetWidth;
    const height = preview.offsetHeight;
    const left = Math.max(gap, Math.min(rect.left, window.innerWidth - width - gap));
    const below = rect.bottom + gap;
    const top = below + height <= window.innerHeight - gap ? below : Math.max(gap, rect.top - height - gap);
    preview.style.left = `${left}px`;
    preview.style.top = `${top}px`;
  }
  function show(link) {
    const item = destination(link);
    if (!item) return;
    active = link;
    image.src = config.images[item.key];
    caption.textContent = item.label;
    preview.hidden = false;
    position();
  }
  image.addEventListener('load', position);
  image.addEventListener('error', hide);
  document.addEventListener('pointerover', event => {
    if (!finePointer.matches || event.pointerType === 'touch') return;
    const link = event.target.closest('a[href]');
    if (link && !link.contains(event.relatedTarget)) { hide(); show(link); }
  });
  document.addEventListener('pointerout', event => {
    if (active && active.contains(event.target) && !active.contains(event.relatedTarget)) hide();
  });
  document.addEventListener('focusin', event => {
    hide();
    const link = event.target.closest('a[href]');
    if (link && link.matches(':focus-visible')) show(link);
  });
  document.addEventListener('focusout', hide);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
  document.addEventListener('click', hide);
  window.addEventListener('scroll', hide, true);
  window.addEventListener('resize', hide);
  finePointer.addEventListener('change', hide);
})();
