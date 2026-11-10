/* Sidebar mascot: a decorative character that shows the pose of the page you are on (the server renders data-pose from the
   active navigation link) and previews other links as you point at them. It reads data-rb-pose from the hovered or
   keyboard-focused sidebar link and copies it to data-pose on the mascot; css/mascot.css does all the drawing and motion.
   When the pointer leaves the navigation links (or keyboard focus leaves them) the mascot returns to the current page's own pose;
   nothing is stored, and a page load always starts from that page's pose. Touch taps do not preview (a tap navigates anyway);
   keyboard focus (:focus-visible) does. A pointer that is merely PARKED over a link does not: right after a page load the
   browser reports the link under a stationary cursor as hovered, which during quick clicking through the sidebar made the
   mascot flash another link's pose before settling on the page's own. A hover only counts once the pointer has really moved
   in this page (movementX/Y), so a new page always starts and stays on its own pose until you point somewhere. It never navigates, focuses, announces, fetches or listens outside the sidebar,
   and the sidebar works the same if this file never loads. Listeners are delegated on the sidebar. */
(() => {
  const mascot = document.querySelector('[data-rb-mascot]');
  const sidebar = document.getElementById('rb-navigation');
  if (!mascot || !sidebar) return;
  const pagePose = mascot.dataset.pose;
  const linkOf = node => (node instanceof Element ? node.closest('.rb-nav-item[data-rb-pose]') : null);
  const show = pose => {
    if (!pose || mascot.dataset.pose === pose) return; // no write (and no style work) when nothing changed
    mascot.dataset.ready = ''; // from the first real preview on, reactions (the one-off wave) may play; arriving never plays them
    mascot.dataset.pose = pose;
  };
  let moved = false; // set by the first real pointer movement in this page, never by the synthetic hover after a load
  const hover = event => {
    if (event.pointerType === 'touch') return;
    if (event.movementX || event.movementY) moved = true;
    const link = linkOf(event.target);
    if (moved && link) { clearTimeout(timer); show(link.dataset.rbPose); }
  };
  // Back to the page's own pose, or to the keyboard-focused link's while one has focus. A short grace period stops the mascot
  // dipping to the page pose while the pointer crosses the small gap between two links; a hover on the next link cancels it.
  let focused = null;
  let timer = 0;
  const rest = () => show(focused ? focused.dataset.rbPose : pagePose);
  sidebar.addEventListener('pointerout', event => {
    if (event.pointerType === 'touch' || linkOf(event.relatedTarget)) return;
    clearTimeout(timer);
    timer = setTimeout(rest, 60);
  });
  sidebar.addEventListener('pointerover', hover);
  sidebar.addEventListener('pointermove', hover, { passive: true });
  sidebar.addEventListener('focusin', event => {
    const link = linkOf(event.target);
    if (!link?.matches(':focus-visible')) return;
    clearTimeout(timer);
    focused = link;
    show(link.dataset.rbPose);
  });
  sidebar.addEventListener('focusout', event => {
    if (linkOf(event.relatedTarget)) return; // moving to another link: its focusin decides
    focused = null;
    rest();
  });
  // Back/forward cache can restore the page with whatever pose it left in; a restored page starts from its own again.
  addEventListener('pageshow', event => { if (event.persisted) { focused = null; show(pagePose); } });
})();
