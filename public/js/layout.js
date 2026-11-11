/* Progressive enhancement: without this script, account/logout and staff links remain visible. */
(() => {
  const shell = document.querySelector('.rb-shell');
  const toggle = document.getElementById('rb-menu-toggle');
  const account = document.getElementById('rb-account-controls');
  const navigation = document.getElementById('rb-navigation');
  const desktop = matchMedia('(min-width: 80rem) and (min-height: 39.5rem)');
  const representative = shell?.classList.contains('rb-shell--rep');
  if (!shell) return;
  const controlled = [account, ...(!representative ? [navigation] : [])].filter(Boolean);
  const setOpen = open => {
    shell.dataset.menuOpen = String(open);
    toggle?.setAttribute('aria-expanded', String(open));
  };
  const sync = () => {
    const focus = document.activeElement;
    const willHideFocus = !desktop.matches && controlled.some(el => el.contains(focus));
    if (toggle) {
      toggle.hidden = desktop.matches;
      // Keep focused controls visible across a breakpoint; otherwise collapse on mobile.
      setOpen(desktop.matches || willHideFocus);
      if (desktop.matches && focus === toggle) account?.querySelector('a, button')?.focus();
    }
    const height = representative && !desktop.matches ? navigation.getBoundingClientRect().height + 16 : 0;
    document.documentElement.style.setProperty('--rb-bottom-space', `${height}px`);
  };
  if (toggle) {
    shell.dataset.menuReady = 'true';
    toggle.addEventListener('click', () => setOpen(shell.dataset.menuOpen !== 'true'));
    shell.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !desktop.matches && shell.dataset.menuOpen === 'true'
          && (event.target === toggle || controlled.some(el => el.contains(event.target)))) {
        event.preventDefault();
        setOpen(false);
        toggle.focus();
      }
    });
  }
  desktop.addEventListener('change', sync);
  // Chromium does not reliably restore this element on history traversal.
  // Keep only coordinates in the existing entry;
  // native links, fragments, URLs and history.scrollRestoration remain untouched.
  const content = shell.querySelector('.rb-shell__content');
  if (shell.classList.contains('rb-shell--workspace')) {
    const key = 'reservebotContentScroll';
    let restoring = false;
    let restoration = 0;
    let saveTimer;
    const saveScroll = () => {
      clearTimeout(saveTimer);
      if (!desktop.matches || restoring) return;
      history.replaceState({ ...history.state, [key]: {
        url: location.href, top: content.scrollTop, left: content.scrollLeft,
      } }, '');
    };
    content.addEventListener('scroll', () => {
      clearTimeout(saveTimer);
      if (!restoring) saveTimer = setTimeout(saveScroll, 100);
    }, { passive: true });
    // Flush before native link navigation, including keyboard-generated clicks.
    document.addEventListener('click', saveScroll, { capture: true });
    addEventListener('pagehide', saveScroll);
    const restoreScroll = saved => {
      clearTimeout(saveTimer);
      const version = ++restoration;
      if (!desktop.matches || saved?.url !== location.href || !Number.isFinite(saved.top) || !Number.isFinite(saved.left)) {
        restoring = false;
        return;
      }
      restoring = true;
      document.fonts.ready.then(() => requestAnimationFrame(() => requestAnimationFrame(() => {
        if (version !== restoration) return;
        if (desktop.matches && saved.url === location.href) content.scrollTo(saved.left, saved.top);
        restoring = false;
      })));
    };
    addEventListener('popstate', event => restoreScroll(event.state?.[key]));
    addEventListener('hashchange', () => {
      // A new native fragment entry has no coordinates for its new URL yet.
      if (history.state?.[key]?.url !== location.href) saveScroll();
    });
    addEventListener('pageshow', event => {
      const type = performance.getEntriesByType('navigation')[0]?.type;
      if (event.persisted || type === 'back_forward' || type === 'reload') restoreScroll(history.state?.[key]);
    });
  }
  if (representative) new ResizeObserver(() => {
    const height = desktop.matches ? 0 : navigation.getBoundingClientRect().height + 16;
    document.documentElement.style.setProperty('--rb-bottom-space', `${height}px`);
  }).observe(navigation);
  sync();
})();

// Header logo expressions (css/brand-logo.css) pause while the tab is hidden. CSS does the animating; this only flags visibility.
(() => {
  const flag = () => document.documentElement.toggleAttribute('data-rb-hidden', document.hidden);
  document.addEventListener('visibilitychange', flag);
  flag();
})();
