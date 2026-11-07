/* Page-only progressive enhancement for the class-check page. After an answer the server redirects back with the result message
   (#check-result, marked autofocus so focus works without this script); this scrolls it into view so it is not left below the fold
   at large text or on a short window. No fetching, routing or shell scroll manipulation. */
(() => {
  const result = document.getElementById('check-result');
  if (!result) return;
  const show = () => {
    result.focus({ preventScroll: true });
    result.scrollIntoView({ block: 'start' });
  };
  if (document.readyState === 'complete') show();
  else window.addEventListener('load', show, { once: true });
})();
