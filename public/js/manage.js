/* Manage pages (progressive enhancement): equipment search clearing and inline category renaming.
   Without script the full list stays visible and "Rename" opens its form through a #fragment. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---- Equipment search: the application's clear control also reloads the list without the search ---- */
  const searchForm = $('form[data-eq-search]');
  if (searchForm) {
    const hadSearch = ($('input[name="q"]', searchForm)?.value || '') !== '';
    $('.rb-search__clear', searchForm)?.addEventListener('click', () => { if (hadSearch) setTimeout(() => searchForm.requestSubmit(), 0); });
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
