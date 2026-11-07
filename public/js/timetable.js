/* Page-only progressive enhancement for a refused timetable form. The server marks the first invalid field `autofocus`
   (so focus works without this script); this brings it into view with its message above it, honouring the field's
   scroll-margin (timetable.css). No fetching, routing or shell scroll manipulation. */
(() => {
  const field = document.querySelector('[autofocus][aria-invalid="true"]');
  if (!field) return;
  const reveal = () => {
    field.focus({ preventScroll: true });
    field.scrollIntoView({ block: 'start' });
  };
  if (document.readyState === 'complete') reveal();
  else window.addEventListener('load', reveal, { once: true });
})();
