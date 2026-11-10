# Sidebar mascot and brand typography

Two small, independent changes. Neither needs the global motion layer, view transitions or any scrolling change.

## Mascot
- One flat SVG (`resources/views/components/ui/mascot.blade.php`) rebuilt from `docs/frontend/ReserveBot-Mascot.jpg`: rounded maroon
  head/body, cream faceplate, two round eyes, a small smile, antenna with a gold tip, capsule arms, stubby legs, gold chest pill.
  Nine poses share that figure; a pose only changes arm angles, one prop and a 1.6px glance. Side-by-side proof:
  `docs/frontend/mascot-evidence/`.
- **Nine poses from `docs/frontend/ReserveBot-Mascot-Poses.png`** (compositions only; its RB belly logo and its changed proportions are
  not used, the gold chest pill stays): idle (relaxed arms), wave (right arm raised, happy eyes, gold sparks), door / calendar /
  clipboard (held in both hands on one shared footprint), key / wrench / ID badge (held in the left hand), attend (arms opened wide, happy
  eyes, sparks, no object). Differences from the sheet: attend has no belly check (it is a pose without an accessory), the objects are
  simplified for 90px, and the arms are straight capsules (the sheet bends them).
- **One holding system**: limbs are drawn over the body and under the faceplate; each arm has a matching hand group (same classes and
  angle variable, drawn after the props) so a grip turns with its arm and cannot detach. Two-handed objects sit on the torso with both
  hands on the side edges (arms -23.4/23.4deg); one-handed objects share one carrier hand at (46.2,108.1) (left arm -28.4deg) and key and
  wrench share one axis through it while the right arm rests. A thin accent edge on hands and limbs keeps an arm readable over the
  same-coloured body. Evidence: `nine-pose-vs-reference.png`, `all-poses-3x.png`, `door-calendar-clipboard-closeup.png`,
  `key-tool-badge-closeup.png`, `mascot-all-poses-actual-size.png`, `sidebar-footer-actual-size.png`, `mascot-reference-vs-new.png`.
- **Pose mapping** (single source, `layouts/partials/navigation.blade.php`, keyed by nav icon): Home wave, Free rooms door, Book / Today
  calendar, My bookings / Requests clipboard, Manage rooms key, Equipment tool, Accounts badge, anything unmapped `attend` (neutral).
  **College Admin** has one link ("Departments", icon `accounts`), so it gets the badge pose with no special case. Pages with no active
  link (My account, password) show the idle figure.
- **Page-aware**: the server renders the active link's pose; `public/js/mascot.js` previews another link's pose on
  hover (mouse/pen) or keyboard focus and returns to the page pose when the pointer or focus leaves the links (60ms grace between neighbours). Nothing is stored, so each page load starts from
  its own pose. bfcache restores start from the page pose.
- **Footer**: hairline, centred 90px figure, faint grounding ellipse. It lives in leftover sidebar height and a container query hides it
  when it will not fit; it is hidden below 80rem wide and on phones, so it can never push or cover a link.
- **Self-contained motion**: its own 180ms transform/opacity transition (`--rbm-duration` in `public/css/mascot.css`), instant under
  `prefers-reduced-motion`. Decorative: `aria-hidden`, `pointer-events: none`, no focusable content.

## Typography
`ReserveBot` is Inter Bold and `PUP College of Engineering` is Inter Medium, but Medium was the only above-the-fold weight not preloaded.
With `font-display: optional` a late face is dropped for the whole page view, so the subtitle could fall back while the heading was Inter.
`layouts/app.blade.php` now preloads all four weights once (woff2, `crossorigin`); `font-display: optional` is unchanged (a swap would
shift layout). `tests/Support/typography-browser.cjs` reproduces the split through Chromium's rendered-font API: 8/8 cold loads split
without the preload under simulated CSS/font latency, 0/8 with it. **Limit**: on a cold first visit where fonts take longer than about
100ms, both lines fall back together (consistent, not split); cached visits are fine.

## Verifying
```
php vendor/bin/phpunit tests/Feature/Frontend/MascotBrandTest.php
node tests/Support/mascot-browser.cjs     <playwright-core>   # poses, hover reset, silhouette, footer, zoom, reduced motion, all roles incl. College Admin
node tests/Support/typography-browser.cjs <playwright-core>   # rendered fonts, cold/warm/slow loads
```
The DB-free checks serve the real shell via `tests/Support/render-mascot.php`; `FrontendConsistencyTest` covers every role over HTTP on the disposable DB.

## Interruption-safe poses (why the mascot used to show mixed frames)
Props, sparks, hands and happy eyes were cross-faded with 180ms opacity transitions. Change pose again inside those 180ms (rapid hovering,
or hovering Home and clicking Today) and the old pose's content was still half visible while the new content faded in: two props at once,
happy eyes and gold sparks over a calendar, hands in mid-air. Arriving on a page was never the cause (nothing animates on arrival).
- Pose content is either fully shown or fully hidden. Leaving a pose hides its content at once; a prop or spark is revealed by a 0s step
  delayed by the arm duration, so it appears once the arms have arrived and a newer pose cancels a pending reveal. The two eye styles swap
  instantly. Hands are part of the arm groups and turn with their arm every frame.
- Only the arms and the smile glide (180ms). All transitions are armed by the first preview (`data-ready`) and live in the
  no-preference media query, so a loaded page never animates and reduced motion is instant. The one-off wave swing was removed: an
  animation that outlives its pose is exactly the kind of leftover this prevents.
- `tests/Support/mascot-monitor.cjs` audits every animation frame (also in every document of a rapid navigation sequence) and
  `transition-frames-before-after.png` shows the old and new frames at fixed times.
