/* Sidebar mascot: a decorative character that shows the pose of the page you are on (the server renders data-pose from the
   active navigation link) and previews other links as you point at them. It reads data-rb-pose from the hovered or
   keyboard-focused sidebar link and copies it to data-pose on the mascot; css/mascot.css does all the drawing and motion.
   The pose then STAYS: leaving the link, moving into the page or clicking in it does nothing, and nothing is stored, so
   the next page load simply starts from that page's own pose. Touch taps do not preview (a tap navigates anyway);
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
    if (moved && link) show(link.dataset.rbPose);
  };
  sidebar.addEventListener('pointerover', hover);
  sidebar.addEventListener('pointermove', hover, { passive: true });
  sidebar.addEventListener('focusin', event => {
    const link = linkOf(event.target);
    if (link?.matches(':focus-visible')) show(link.dataset.rbPose);
  });
  // Back/forward cache can restore the page with whatever pose it left in; a restored page starts from its own again.
  addEventListener('pageshow', event => { if (event.persisted) show(pagePose); });
})();
