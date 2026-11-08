/* Manage pages (progressive enhancement): equipment search and inline category renaming.
   Without script the full list stays visible and "Rename" opens its form through a #fragment. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---- Equipment search: filters what is shown; never changes data ---- */
  const box = $('[data-eq-search]');
  if (box) {
    box.hidden = false;
    const input = $('input', box), status = $('[role="status"]', box);
    const sections = $$('[data-eq-section]');
    const none = $('[data-eq-none]');
    const run = () => {
      const terms = input.value.toLowerCase().split(/\s+/).filter(Boolean);
      let shownItems = 0, shownSections = 0;
      sections.forEach(section => {
        const category = ($('[data-eq-category]', section)?.textContent || '').toLowerCase();
        const rows = $$('[data-eq-row]', section);
        const categoryHit = terms.length > 0 && terms.every(t => category.includes(t));
        let any = 0;
        rows.forEach(row => {
          const hay = `${row.querySelector('[data-eq-name]')?.textContent || ''} ${category}`.toLowerCase();
          const hit = terms.every(t => hay.includes(t));
          row.hidden = !hit;
          if (hit) any++;
        });
        const show = terms.length === 0 || any > 0 || (categoryHit && rows.length === 0);
        section.hidden = !show;
        if (show) { shownSections++; shownItems += any; }
      });
      none.hidden = terms.length === 0 || shownSections > 0;
      status.textContent = terms.length === 0 ? '' : shownSections === 0 ? 'No matches.'
        : `${shownItems} ${shownItems === 1 ? 'item' : 'items'} in ${shownSections} ${shownSections === 1 ? 'category' : 'categories'}.`;
    };
    input.addEventListener('input', run);
    input.addEventListener('search', run);
    input.addEventListener('keydown', e => { if (e.key === 'Escape' && input.value) { input.value = ''; input.dispatchEvent(new Event('input', { bubbles: true })); } });
    if (input.value) run();
  }

  /* ---- Inline category rename ---- */
  $$('.mcat-rename').forEach(form => {
    const input = $('input[name="name"]', form);
    const error = $('.field-error', form);
    const original = form.dataset.original || '';
    const showError = message => {
      error.textContent = message;
      error.hidden = !message;
      if (message) { input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', error.id); }
      else { input.removeAttribute('aria-invalid'); }
    };
    form.addEventListener('submit', event => {
      if (!input.value.trim()) { event.preventDefault(); showError('Enter a name for the category.'); input.focus(); }
      else if (input.value.length > 60) { event.preventDefault(); showError('Keep the name to 60 characters or fewer.'); input.focus(); }
    });
    input.addEventListener('input', () => { if (!error.hidden) showError(''); });
    form.addEventListener('rb-reveal', event => {
      if (!event.detail.open) { input.value = original; showError(''); }
    });
    // The category name is the rename target: double-click with a mouse; tap with touch or pen; Enter, Space or F2 from the keyboard.
    const title = document.querySelector(`[data-rb-rename="${form.id}"]`);
    if (title) {
      let pointer = 'mouse';
      const open = () => { getSelection()?.removeAllRanges(); window.rbReveal?.setOpen(form, true); };
      title.setAttribute('role', 'button');
      title.addEventListener('pointerdown', event => { pointer = event.pointerType || 'mouse'; });
      title.addEventListener('click', event => {
        event.preventDefault();                                  // never navigate to the #fragment fallback
        if (event.detail === 0 || pointer !== 'mouse') open();   // keyboard / assistive technology / touch / pen
        else { title.classList.add('is-hinting'); setTimeout(() => title.classList.remove('is-hinting'), 2500); }
      });
      title.addEventListener('dblclick', event => { event.preventDefault(); open(); });
      title.addEventListener('keydown', event => {
        if (event.key === 'F2' || event.key === ' ') { event.preventDefault(); open(); }
      });
      // Returning from Save-less closing (Cancel / Escape) puts focus back on the name.
      form.addEventListener('rb-reveal', event => { if (!event.detail.open) setTimeout(() => title.focus(), 0); });
    }
  });
})();
