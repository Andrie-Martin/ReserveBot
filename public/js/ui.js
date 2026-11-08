/* Shared progressive enhancement for ReserveBot (no framework, no network).
   Every control here has a working server-rendered / native fallback; the script only improves it:
   - drawers:  <dialog class="rb-drawer"> opened by <a href="#id" data-rb-drawer="id">   (fallback: :target)
   - pickers:  <select data-rb-picker[="search"]> gets a compact, scrollable, keyboard-friendly list (the select stays
               in the form and remains the submitted value; without script the native select is used)
   - reveals:  <a href="#id" data-rb-reveal="id"> shows/hides a panel inline                (fallback: :target) */
(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  let counter = 0;
  const uid = prefix => `${prefix}-${++counter}`;
  const plainClick = event => event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;
  const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  /* ------------------------------------------------------------------ drawers */
  const drawers = () => {
    const opened = new WeakMap(); // dialog -> element that opened it
    const clean = () => history.replaceState(history.state, '', location.pathname + location.search);
    const open = (dialog, trigger) => {
      if (!dialog || dialog.open || typeof dialog.showModal !== 'function') return;
      opened.set(dialog, trigger || document.activeElement);
      dialog.showModal();
      trigger?.setAttribute('aria-expanded', 'true');
    };
    const hookDialog = dialog => {
      dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); }); // backdrop
      dialog.addEventListener('keydown', event => { // keep Tab inside the panel (never out to the page or browser chrome)
        if (event.key !== 'Tab') return;
        const stops = $$(FOCUSABLE, dialog).filter(el => el.offsetParent !== null || el === document.activeElement);
        if (!stops.length) return;
        const first = stops[0], last = stops[stops.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      });
      dialog.addEventListener('close', () => {
        const trigger = opened.get(dialog);
        trigger?.setAttribute('aria-expanded', 'false');
        if (trigger && document.contains(trigger)) trigger.focus({ preventScroll: true });
      });
    };
    $$('dialog.rb-drawer').forEach(hookDialog);
    document.addEventListener('click', event => {
      const closer = event.target.closest('[data-rb-drawer-close]');
      if (closer) { event.preventDefault(); closer.closest('dialog')?.close(); return; }
      const trigger = event.target.closest('[data-rb-drawer]');
      if (!trigger || !plainClick(event)) return;
      const dialog = document.getElementById(trigger.dataset.rbDrawer);
      if (!dialog || typeof dialog.showModal !== 'function') return; // keep the native #fragment fallback
      event.preventDefault();
      open(dialog, trigger);
    });
    $$('[data-rb-drawer]').forEach(trigger => {
      trigger.setAttribute('aria-haspopup', 'dialog');
      trigger.setAttribute('aria-expanded', 'false');
    });
    // A bookmarked #fragment that names a drawer opens it properly (modal) without leaving the hash behind.
    const fromHash = () => {
      const dialog = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
      if (dialog?.matches('dialog.rb-drawer') && !dialog.open) {
        clean();
        open(dialog, $(`[data-rb-drawer="${dialog.id}"]`));
      }
    };
    fromHash();
  };

  /* ------------------------------------------------------------------ reveal panels */
  const reveals = () => {
    const panels = $$('.rb-reveal');
    const triggerFor = panel => $$(`[data-rb-reveal="${panel.id}"]`);
    const sync = panel => triggerFor(panel).forEach(trigger => trigger.setAttribute('aria-expanded', String(panel.classList.contains('is-open'))));
    const setOpen = (panel, open, { focus = true } = {}) => {
      panel.classList.toggle('is-open', open);
      panel.dispatchEvent(new CustomEvent('rb-reveal', { bubbles: true, detail: { open } }));
      sync(panel);
      if (open && focus) ($('[data-rb-focus]', panel) || $('textarea, input:not([type="hidden"])', panel))?.focus();
      if (!open && focus) triggerFor(panel).find(t => t.offsetParent !== null)?.focus();
    };
    panels.forEach(panel => {
      triggerFor(panel).forEach(trigger => {
        trigger.setAttribute('role', 'button');
        trigger.setAttribute('aria-controls', panel.id);
        trigger.addEventListener('keydown', event => { if (event.key === ' ') { event.preventDefault(); trigger.click(); } });
      });
      sync(panel);
      panel.addEventListener('keydown', event => {
        if (event.key === 'Escape' && panel.classList.contains('is-open') && !event.defaultPrevented) {
          event.preventDefault();
          setOpen(panel, false);
        }
      });
    });
    document.addEventListener('click', event => {
      const closer = event.target.closest('[data-rb-reveal-close]');
      if (closer) {
        const panel = closer.closest('.rb-reveal');
        if (panel) { event.preventDefault(); panel.reset?.(); setOpen(panel, false); }
        return;
      }
      const trigger = event.target.closest('[data-rb-reveal]');
      if (!trigger || !plainClick(event)) return;
      const panel = document.getElementById(trigger.dataset.rbReveal);
      if (!panel) return;
      event.preventDefault();
      setOpen(panel, !panel.classList.contains('is-open'));
    });
    // A native #fragment (bookmark or no-script link) that targets a panel becomes ordinary open state.
    const target = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
    if (target?.classList.contains('rb-reveal')) {
      history.replaceState(history.state, '', location.pathname + location.search);
      setOpen(target, true, { focus: false });
    }
    // Opening on the server (refused save) is announced by focus, if the markup asks for it.
    $$('.rb-reveal.is-open [autofocus]').forEach(el => el.focus());
    window.rbReveal = { setOpen };
  };

  /* ------------------------------------------------------------------ pickers (select replacement) */
  const pickers = () => {
    const open = { current: null };
    const closeCurrent = (restore = true) => open.current?.close(restore);
    document.addEventListener('pointerdown', event => {
      if (open.current && !open.current.root.contains(event.target)) closeCurrent(false);
    });
    window.addEventListener('resize', () => open.current?.position());
    document.addEventListener('scroll', event => {
      if (open.current && !open.current.popup.contains(event.target)) open.current.position();
    }, true);

    const enhance = select => {
      if (select.dataset.rbPickerReady || select.multiple) return;
      select.dataset.rbPickerReady = 'true';
      const searchable = select.dataset.rbPicker === 'search';
      const label = select.id ? $(`label[for="${CSS.escape(select.id)}"]`) : null;
      if (label && !label.id) label.id = uid('rb-picker-label');

      const root = document.createElement('div');
      root.className = 'rb-picker';
      select.before(root);
      root.append(select);
      select.classList.add('rb-picker__native');
      select.tabIndex = -1;
      select.setAttribute('aria-hidden', 'true');

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'rb-picker__button';
      button.setAttribute('aria-haspopup', 'listbox');
      button.setAttribute('aria-expanded', 'false');
      const value = document.createElement('span');
      value.className = 'rb-picker__value';
      value.id = uid('rb-picker-value');
      const chevron = document.createElement('span');
      chevron.className = 'rb-picker__chevron';
      chevron.setAttribute('aria-hidden', 'true');
      button.append(value, chevron);
      const describedBy = select.getAttribute('aria-describedby');
      if (describedBy) button.setAttribute('aria-describedby', describedBy);
      if (select.getAttribute('aria-invalid') === 'true') button.setAttribute('aria-invalid', 'true');
      button.setAttribute('aria-labelledby', [label?.id, value.id].filter(Boolean).join(' '));
      if (!label) button.setAttribute('aria-label', select.getAttribute('aria-label') || 'Choose');

      const popup = document.createElement('div');
      popup.className = 'rb-picker__popup';
      popup.hidden = true;
      const listId = uid('rb-picker-list');
      let search = null, status = null;
      if (searchable) {
        search = document.createElement('input');
        search.type = 'search';
        search.className = 'rb-picker__search';
        search.setAttribute('role', 'combobox');
        search.setAttribute('aria-expanded', 'true');
        search.setAttribute('aria-controls', listId);
        search.setAttribute('aria-autocomplete', 'list');
        search.setAttribute('aria-label', select.dataset.rbSearchLabel || 'Search');
        search.placeholder = select.dataset.rbSearchLabel || 'Search';
        search.autocomplete = 'off';
        popup.append(search);
        status = document.createElement('p');
        status.className = 'sr-only';
        status.setAttribute('role', 'status');
        popup.append(status);
      }
      const list = document.createElement('div');
      list.className = 'rb-picker__list';
      list.id = listId;
      list.setAttribute('role', 'listbox');
      if (label) list.setAttribute('aria-labelledby', label.id); else list.setAttribute('aria-label', select.getAttribute('aria-label') || 'Choose');
      popup.append(list);
      const empty = document.createElement('p');
      empty.className = 'rb-picker__empty';
      empty.hidden = true;
      empty.textContent = select.dataset.rbEmpty || 'Nothing matches.';
      popup.append(empty);
      root.append(button, popup);

      // Build the options from the native select (optgroups become headed groups).
      const items = [];
      const makeOption = (option, group) => {
        const element = document.createElement('div');
        element.id = uid('rb-opt');
        element.setAttribute('role', 'option');
        element.className = 'rb-picker__option';
        if (option.value === '') element.classList.add('is-placeholder');
        if (option.disabled) { element.setAttribute('aria-disabled', 'true'); element.classList.add('is-disabled'); }
        const title = option.dataset.title || option.textContent.trim();
        const meta = option.dataset.meta || '';
        const strong = document.createElement('span');
        strong.className = 'rb-picker__option-title';
        strong.textContent = title;
        element.append(strong);
        if (meta) {
          const small = document.createElement('span');
          small.className = 'rb-picker__option-meta';
          small.textContent = meta;
          element.append(small);
        }
        const item = { option, element, group, text: `${title} ${meta} ${group || ''}`.toLowerCase(), shown: option.dataset.display || title };
        element.addEventListener('click', () => { if (!option.disabled) choose(item); });
        element.addEventListener('pointermove', () => { if (!element.classList.contains('is-active')) activate(item, false); });
        items.push(item);
        return element;
      };
      const groupHeaders = [];
      [...select.children].forEach(child => {
        if (child.tagName === 'OPTGROUP') {
          const group = document.createElement('div');
          group.setAttribute('role', 'group');
          group.className = 'rb-picker__group';
          const heading = document.createElement('div');
          heading.className = 'rb-picker__group-label';
          heading.id = uid('rb-grp');
          heading.textContent = child.label;
          group.setAttribute('aria-labelledby', heading.id);
          group.append(heading);
          [...child.children].forEach(option => group.append(makeOption(option, child.label)));
          list.append(group);
          groupHeaders.push(group);
        } else if (child.tagName === 'OPTION') {
          list.append(makeOption(child, ''));
        }
      });

      let activeItem = null;
      const visible = () => items.filter(item => !item.element.hidden && !item.option.disabled);
      const activate = (item, scroll = true) => {
        activeItem?.element.classList.remove('is-active');
        activeItem = item || null;
        const focusTarget = searchable ? search : list;
        if (item) {
          item.element.classList.add('is-active');
          focusTarget.setAttribute('aria-activedescendant', item.element.id);
          if (scroll) item.element.scrollIntoView({ block: 'nearest' });
        } else focusTarget.removeAttribute('aria-activedescendant');
      };
      const sync = () => {
        const selected = items.find(item => item.option.value === select.value && item.option.selected) || items.find(item => item.option.selected);
        items.forEach(item => item.element.setAttribute('aria-selected', String(item === selected)));
        value.textContent = selected ? selected.shown : '';
        root.classList.toggle('is-placeholder', !selected || selected.option.value === '');
        button.disabled = select.disabled;
        return selected;
      };
      const choose = item => {
        select.value = item.option.value;
        sync();
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
        api.close(true);
      };
      const position = () => {
        const rect = button.getBoundingClientRect();
        const gap = 4, margin = 8;
        const below = window.innerHeight - rect.bottom - margin - gap;
        const above = rect.top - margin - gap;
        const wanted = Math.min(popup.scrollHeight || 320, 320);
        const up = below < Math.min(wanted, 220) && above > below;
        const room = Math.max(120, up ? above : below);
        const header = search ? search.offsetHeight + 16 : 0;
        list.style.maxHeight = `${Math.max(96, Math.min(280, room - header))}px`;
        popup.style.width = `${Math.max(rect.width, 0)}px`;
        popup.style.left = `${Math.min(Math.max(margin, rect.left), Math.max(margin, window.innerWidth - rect.width - margin))}px`;
        popup.style.top = up ? '' : `${rect.bottom + gap}px`;
        popup.style.bottom = up ? `${window.innerHeight - rect.top + gap}px` : '';
        popup.classList.toggle('is-up', up);
      };
      const filter = () => {
        const terms = (search?.value || '').toLowerCase().split(/\s+/).filter(Boolean);
        items.forEach(item => { item.element.hidden = terms.length > 0 && (item.option.value === '' || !terms.every(term => item.text.includes(term))); });
        groupHeaders.forEach(group => { group.hidden = !$$('.rb-picker__option', group).some(el => !el.hidden); });
        const count = items.filter(item => !item.element.hidden && item.option.value !== '').length;
        empty.hidden = count > 0 || terms.length === 0;
        if (status) status.textContent = terms.length ? `${count} ${count === 1 ? 'result' : 'results'}` : '';
        activate(visible().find(item => item.option.value !== '') || visible()[0] || null, false);
        position();
      };
      const move = (delta, to) => {
        const choices = visible();
        if (!choices.length) return;
        let index = activeItem ? choices.indexOf(activeItem) : -1;
        index = to === 'start' ? 0 : to === 'end' ? choices.length - 1 : Math.min(choices.length - 1, Math.max(0, index + delta));
        activate(choices[index]);
      };

      let typed = '', typedTimer;
      const onKeys = event => {
        switch (event.key) {
          case 'ArrowDown': event.preventDefault(); move(1); break;
          case 'ArrowUp': event.preventDefault(); move(-1); break;
          case 'Home': if (!searchable) { event.preventDefault(); move(0, 'start'); } break;
          case 'End': if (!searchable) { event.preventDefault(); move(0, 'end'); } break;
          case 'PageDown': event.preventDefault(); move(6); break;
          case 'PageUp': event.preventDefault(); move(-6); break;
          case 'Enter': event.preventDefault(); if (activeItem) choose(activeItem); break;
          case ' ': if (!searchable) { event.preventDefault(); if (activeItem) choose(activeItem); } break;
          case 'Escape': event.preventDefault(); event.stopPropagation(); api.close(true); break;
          case 'Tab': api.close(false); break;
          default:
            if (!searchable && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
              typed += event.key.toLowerCase();
              clearTimeout(typedTimer);
              typedTimer = setTimeout(() => { typed = ''; }, 700);
              const found = visible().find(item => item.shown.toLowerCase().startsWith(typed)) || visible().find(item => item.shown.toLowerCase().includes(typed));
              if (found) activate(found);
            }
        }
      };
      list.tabIndex = -1;
      list.addEventListener('keydown', onKeys);
      search?.addEventListener('keydown', onKeys);
      search?.addEventListener('input', filter);

      const api = {
        root, popup, position,
        close(restore) {
          if (popup.hidden) return;
          popup.hidden = true;
          button.setAttribute('aria-expanded', 'false');
          root.classList.remove('is-open');
          if (open.current === api) open.current = null;
          if (restore) button.focus({ preventScroll: true });
        },
        open() {
          if (select.disabled || !popup.hidden) return;
          closeCurrent(false);
          popup.hidden = false;
          open.current = api;
          button.setAttribute('aria-expanded', 'true');
          root.classList.add('is-open');
          if (search) { search.value = ''; filter(); }
          items.forEach(item => { item.element.hidden = false; });
          groupHeaders.forEach(group => { group.hidden = false; });
          empty.hidden = true;
          position();
          const selected = sync();
          activate(selected && !selected.option.disabled ? selected : visible()[0] || null);
          (search || list).focus({ preventScroll: true });
          position();
        },
      };
      button.addEventListener('click', () => (popup.hidden ? api.open() : api.close(true)));
      button.addEventListener('keydown', event => {
        if (['ArrowDown', 'ArrowUp'].includes(event.key) || (!searchable && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey && event.key !== ' ')) {
          event.preventDefault();
          api.open();
          if (event.key.length === 1) onKeys(event);
        }
      });
      // Programmatic focus (label click, first invalid control) lands on the visible button.
      select.addEventListener('focus', () => button.focus());
      select.addEventListener('invalid', () => { root.classList.add('is-invalid'); button.setAttribute('aria-invalid', 'true'); });
      select.addEventListener('change', () => { root.classList.remove('is-invalid'); if (select.getAttribute('aria-invalid') !== 'true') button.removeAttribute('aria-invalid'); sync(); });
      select.form?.addEventListener('reset', () => setTimeout(sync));
      sync();
    };
    $$('select[data-rb-picker]').forEach(enhance);
  };

  const start = () => { drawers(); reveals(); pickers(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
