// Login mascot: pause its CSS loop while the tab is hidden (css/login-mascot.css reads data-rb-hidden). Nothing else; no timers.
(function () {
  var root = document.documentElement;
  function sync() { if (document.hidden) { root.setAttribute('data-rb-hidden', ''); } else { root.removeAttribute('data-rb-hidden'); } }
  document.addEventListener('visibilitychange', sync);
  sync();
})();
