/* Page-only progressive enhancement. Server-rendered records and native URLs
   remain the source of truth; no routing, fetching or shell scroll manipulation. */
(() => {
  const panel = document.querySelector('.rb-availability__calendar-details');
  if (!panel) return;
  const groups = [...panel.querySelectorAll('.rb-availability__calendar-events')];
  const links = [...document.querySelectorAll('.rb-availability__event-count')];
  const prompt = panel.querySelector('.rb-availability__event-prompt');
  const announcement = panel.querySelector('[data-event-announcement]');
  const select = (id, focus = false) => {
    const selected = groups.find(group => group.id === id);
    groups.forEach(group => {
      group.hidden = group !== selected;
      if (group === selected) group.open = true;
    });
    links.forEach(link => link.setAttribute('aria-expanded', String(link.hash === `#${selected?.id}`)));
    prompt.hidden = Boolean(selected);
    announcement.textContent = selected ? `Showing ${selected.querySelector('summary').textContent.trim()}.` : '';
    if (focus && selected) selected.querySelector('summary').focus();
  };
  groups.forEach(group => group.addEventListener('toggle', () => {
    const link = links.find(candidate => candidate.hash === `#${group.id}`);
    link?.setAttribute('aria-expanded', String(!group.hidden && group.open));
  }));
  links.forEach(link => {
    link.setAttribute('role', 'button');
    link.setAttribute('aria-controls', link.hash.slice(1));
    link.addEventListener('click', event => {
      // Modified activation keeps the native link destination available.
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      select(link.hash.slice(1), true);
    });
    link.addEventListener('keydown', event => {
      if (event.key === ' ') { event.preventDefault(); link.click(); }
    });
  });
  // Direct/bookmarked native event fragments still reveal the corresponding date.
  // Ordinary selections are local panel state and create no history entries.
  const fromLocation = () => select(location.hash.slice(1));
  addEventListener('hashchange', fromLocation);
  addEventListener('popstate', fromLocation);
  fromLocation();
})();
