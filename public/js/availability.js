/* Page-only progressive enhancement. Server-rendered records and native URLs
   remain the source of truth; no routing, fetching or shell scroll manipulation.
   Without this script every date with events is listed and the calendar's event links jump to their date. */
(() => {
  const panel = document.querySelector('.rb-availability__calendar-details');
  if (!panel) return;
  const groups = [...panel.querySelectorAll('.rb-availability__calendar-events')];
  const links = [...document.querySelectorAll('.rb-availability__event-count')];
  const prompt = panel.querySelector('.rb-availability__event-prompt');
  const announcement = panel.querySelector('[data-event-announcement]');
  const scroller = panel.querySelector('.rb-availability__events-scroll');
  const pickedDate = document.querySelector('.rb-availability__selected .rb-availability__event-count')?.hash.slice(1) || '';
  const select = (id, focus = false) => {
    const selected = groups.find(group => group.id === id);
    groups.forEach(group => { group.hidden = group !== selected; });
    links.forEach(link => {
      if (link.hash === `#${selected?.id}`) link.setAttribute('aria-current', 'true');
      else link.removeAttribute('aria-current');
    });
    if (prompt) prompt.hidden = Boolean(selected) || groups.length === 0;
    if (scroller) scroller.scrollTop = 0;
    const title = selected?.querySelector('h3');
    announcement.textContent = selected ? `Showing ${title.textContent.trim()}, ${selected.querySelector('.rb-availability__events-count').textContent.trim()}.` : '';
    if (focus && title) title.focus({ preventScroll: true });
  };
  links.forEach(link => {
    link.setAttribute('aria-controls', link.hash.slice(1));
    link.addEventListener('click', event => {
      // Modified activation keeps the native link destination available.
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      select(link.hash.slice(1), true);
    });
  });
  // A direct/bookmarked fragment shows that date; otherwise the day picked in the calendar, when it has events.
  // Ordinary selections are local panel state and create no history entries.
  const fromLocation = () => select(location.hash.startsWith('#events-') ? location.hash.slice(1) : pickedDate);
  addEventListener('hashchange', fromLocation);
  addEventListener('popstate', fromLocation);
  fromLocation();
})();
