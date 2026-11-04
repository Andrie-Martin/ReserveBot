/* Progressive enhancement: without this script, account/logout and staff links remain visible. */
(() => {
  const shell = document.querySelector('.rb-shell');
  const toggle = document.getElementById('rb-menu-toggle');
  const account = document.getElementById('rb-account-controls');
  const navigation = document.getElementById('rb-navigation');
  const desktop = matchMedia('(min-width: 80rem)');
  const representative = shell?.classList.contains('rb-shell--rep');
  if (!shell) return;
  // The normal-flow header can wrap. Use its remaining visible height rather than
  // a fixed offset, so the sticky sidebar fills only the available viewport.
  const header = shell.querySelector('.rb-header');
  const sizeSidebar = () => {
    if (!navigation || !desktop.matches) {
      document.documentElement.style.removeProperty('--rb-sidebar-height');
      return;
    }
    const top = Math.max(0, header.getBoundingClientRect().bottom);
    document.documentElement.style.setProperty('--rb-sidebar-height', `${Math.max(0, innerHeight - top)}px`);
  };
  let sidebarFrame = 0;
  const queueSidebarSize = () => {
    if (sidebarFrame) return;
    sidebarFrame = requestAnimationFrame(() => { sidebarFrame = 0; sizeSidebar(); });
  };
  if (navigation) {
    addEventListener('scroll', queueSidebarSize, { passive: true });
    addEventListener('resize', queueSidebarSize);
    const observer = new ResizeObserver(queueSidebarSize);
    observer.observe(header);
    observer.observe(shell.querySelector('.rb-masthead'));
  }
  const controlled = [account, ...(!representative ? [navigation] : [])].filter(Boolean);
  const setOpen = open => {
    shell.dataset.menuOpen = String(open);
    toggle?.setAttribute('aria-expanded', String(open));
  };
  const sync = () => {
    sizeSidebar();
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
  if (representative) new ResizeObserver(() => {
    const height = desktop.matches ? 0 : navigation.getBoundingClientRect().height + 16;
    document.documentElement.style.setProperty('--rb-bottom-space', `${height}px`);
  }).observe(navigation);
  sync();
})();
