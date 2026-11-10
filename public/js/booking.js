/* Book (step 2) progressive enhancement: quantity steppers, equipment search and a live "selected equipment" summary.
   The form works without this file: each item is a plain number input named qty[id], limited by min/max. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const steppers = $$('[data-stepper]');
  if (!steppers.length) return;

  const clamp = input => {
    const max = Number.isFinite(parseInt(input.max, 10)) ? parseInt(input.max, 10) : 99;
    let value = parseInt(input.value, 10);
    if (!Number.isFinite(value) || value < 0) value = 0;
    return Math.min(value, max);
  };

  const summary = $('[data-eq-summary]');
  const summaryList = $('[data-eq-summary-list]');
  const summaryEmpty = $('[data-eq-summary-empty]');

  const refresh = () => {
    const picked = [];
    $$('.eq-cat').forEach(category => {
      let count = 0;
      $$('[data-eq-item]', category).forEach(item => {
        const input = $('.rb-stepper__input', item);
        const value = clamp(input);
        const [minus, plus] = $$('.rb-stepper__btn', item);
        if (minus) minus.disabled = input.disabled || value <= 0;
        if (plus) plus.disabled = input.disabled || value >= (parseInt(input.max, 10) || 0);
        item.classList.toggle('is-picked', value > 0);
        if (value > 0) { count++; picked.push({ name: item.dataset.eqName, value }); }
      });
      let badge = $('.eq-picked', category);
      if (count > 0) {
        if (!badge) { badge = document.createElement('span'); badge.className = 'eq-picked'; $('summary', category).append(badge); }
        badge.textContent = `${count} picked`;
      } else badge?.remove();
    });
    if (!summary) return;
    summaryList.replaceChildren(...picked.map(entry => {
      const li = document.createElement('li');
      const name = document.createElement('span');
      name.textContent = entry.name;
      const qty = document.createElement('strong');
      qty.textContent = `× ${entry.value}`;
      li.append(name, qty);
      return li;
    }));
    summaryEmpty.hidden = picked.length > 0;
  };

  steppers.forEach(stepper => {
    const input = $('.rb-stepper__input', stepper);
    stepper.classList.add('rb-stepper--js');
    $$('.rb-stepper__btn', stepper).forEach(button => {
      button.hidden = false;
      button.addEventListener('click', () => {
        input.value = Math.max(0, Math.min(parseInt(input.max, 10) || 0, clamp(input) + parseInt(button.dataset.step, 10)));
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    input.addEventListener('input', refresh);
    input.addEventListener('change', () => { input.value = clamp(input); refresh(); });
    input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); input.value = clamp(input); refresh(); } });
  });
  if (summary) summary.hidden = false;
  refresh();

  /* ---- search ---- */
  const tools = $('[data-eq-tools]');
  if (tools) {
    tools.hidden = false;
    const search = $('input', tools), status = $('[data-eq-status]', tools), none = $('[data-eq-none]');
    const categories = $$('.eq-cat');
    const wasOpen = new Map(categories.map(category => [category, category.open]));
    let searching = false;
    const run = () => {
      const terms = search.value.toLowerCase().split(/\s+/).filter(Boolean);
      if (terms.length && !searching) categories.forEach(category => wasOpen.set(category, category.open));
      let shown = 0;
      categories.forEach(category => {
        const label = (category.dataset.eqCategory || '').toLowerCase();
        let any = 0;
        $$('[data-eq-item]', category).forEach(item => {
          const hit = terms.every(term => `${item.dataset.eqName} ${label}`.toLowerCase().includes(term));
          item.hidden = !hit;
          if (hit) any++;
        });
        category.hidden = terms.length > 0 && any === 0;
        if (terms.length) category.open = any > 0;
        shown += any;
      });
      if (!terms.length && searching) categories.forEach(category => { category.open = wasOpen.get(category); });
      searching = terms.length > 0;
      none.hidden = !terms.length || shown > 0;
      status.textContent = terms.length ? `${shown} ${shown === 1 ? 'item matches' : 'items match'}.` : '';
    };
    search.addEventListener('input', run);
    search.addEventListener('keydown', event => {
      if (event.key === 'Enter') event.preventDefault(); // never submits the booking
      if (event.key === 'Escape' && search.value) { search.value = ''; search.dispatchEvent(new Event('input', { bubbles: true })); }
    });
  }
})();

/* Borrower: the name and ID number are needed only when the booking includes equipment. The server checks this
   either way; here the two boxes are just made required in the browser while "Room only" is not picked, so the
   browser points at an empty box before the form is sent (a hidden required box would block sending instead). */
(() => {
  'use strict';
  const fields = [...document.querySelectorAll('[data-borrower-field]')];
  const needs = [...document.querySelectorAll('.step2 input[name=need]')];
  if (!fields.length || !needs.length) return;
  const sync = () => {
    const need = needs.find(option => option.checked)?.value;
    fields.forEach(field => { field.required = need !== 'room'; });
  };
  needs.forEach(option => option.addEventListener('change', sync));
  sync();
})();
