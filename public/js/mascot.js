/* Sidebar mascot: a decorative character that shows the pose of the page you are on (the server renders data-pose from the
   active navigation link) and previews other links as you point at them. It reads data-rb-pose from the hovered or
   keyboard-focused sidebar link and copies it to data-pose on the mascot; css/mascot.css does all the drawing and motion.
   The pose then STAYS: leaving the link, moving into the page or clicking in it does nothing, and nothing is stored, so
   the next page load simply starts from that page's own pose. Touch taps do not preview (a tap navigates anyway);
   keyboard focus (:focus-visible) does. It never navigates, focuses, announces, fetches or listens outside the sidebar,
   and the sidebar works the same if this file never loads. Listeners are delegated on the sidebar. */
(() => {
  const mascot = document.querySelector('[data-rb-mascot]');
  const sidebar = document.getElementById('rb-navigation');
  if (!mascot || !sidebar) return;
  const pagePose = mascot.dataset.pose;
  const linkOf = node => (node instanceof Element ? node.closest('.rb-nav-item[data-rb-pose]') : null);
  const show = pose => {
    if (pose && mascot.dataset.pose !== pose) mascot.dataset.pose = pose; // no write (and no style work) when nothing changed
  };
  sidebar.addEventListener('pointerover', event => {
    const link = linkOf(event.target);
    if (link && event.pointerType !== 'touch') show(link.dataset.rbPose);
  });
  sidebar.addEventListener('focusin', event => {
    const link = linkOf(event.target);
    if (link?.matches(':focus-visible')) show(link.dataset.rbPose);
  });
  // Back/forward cache can restore the page with whatever pose it left in; a restored page starts from its own again.
  addEventListener('pageshow', event => { if (event.persisted) show(pagePose); });
  // Animations that only make sense as a reaction (the one-off wave) are armed after the first frame, never on arrival.
  requestAnimationFrame(() => requestAnimationFrame(() => { mascot.dataset.ready = ''; }));
})();
