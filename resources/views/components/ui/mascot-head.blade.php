@props(['animated' => false])
{{-- ReserveBot's header logo: a simplified version of the sidebar mascot's head (maroon head, cream faceplate, round eyes, small
     smile, short antenna with a gold tip; same tokens, no body, arms or sparks). Decorative: the brand link's text names it.
     Static by default. `animated` only marks it as eligible for the expression loop; public/css/brand-logo.css runs the loop
     ONLY inside a workspace page whose sidebar mascot is hidden (the exact complement of the sidebar's own media query), never
     for guests or the login page, and never under prefers-reduced-motion.
     Expressions are whole-face overlays (cream faceplate + eyes + mouth), so a later layer fully covers the normal face and two
     expressions can never show at once. The head and antenna are drawn once and never move. viewBox 48x48. --}}
<svg {{ $attributes->class(['rbh']) }} viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false" @if ($animated) data-rb-head-expressive @endif>
  <line class="rbh-aerial" x1="24" y1="6" x2="24" y2="13"/>
  <circle class="rbh-gold" cx="24" cy="6" r="3.6"/>
  <rect class="rbh-maroon" x="2" y="12" width="44" height="34" rx="12"/>
  <rect class="rbh-cream" x="7" y="19" width="34" height="20" rx="9"/>
  <circle class="rbh-maroon" cx="17" cy="26.5" r="2.7"/>
  <circle class="rbh-maroon" cx="31" cy="26.5" r="2.7"/>
  <path class="rbh-line" d="M20.5 32Q24 34.8 27.5 32"/>
  @if ($animated)
    <g class="rbh-x rbh-x--blink">
      <rect class="rbh-cream" x="7" y="19" width="34" height="20" rx="9"/>
      <path class="rbh-line" d="M14.5 27h5M28.5 27h5M20.5 32Q24 34.8 27.5 32"/>
    </g>
    <g class="rbh-x rbh-x--happy">
      <rect class="rbh-cream" x="7" y="19" width="34" height="20" rx="9"/>
      <path class="rbh-line" d="M14 28.5Q17 23.5 20 28.5M28 28.5Q31 23.5 34 28.5M20.5 32Q24 34.8 27.5 32"/>
    </g>
    <g class="rbh-x rbh-x--curious">
      <rect class="rbh-cream" x="7" y="19" width="34" height="20" rx="9"/>
      <circle class="rbh-maroon" cx="17" cy="26" r="3.6"/>
      <circle class="rbh-maroon" cx="31" cy="27.2" r="1.7"/>
      <path class="rbh-line" d="M20.5 32Q24 34.8 27.5 32"/>
    </g>
    <g class="rbh-x rbh-x--surprised">
      <rect class="rbh-cream" x="7" y="19" width="34" height="20" rx="9"/>
      <circle class="rbh-maroon" cx="17" cy="26" r="3.6"/>
      <circle class="rbh-maroon" cx="31" cy="26" r="3.6"/>
      <ellipse class="rbh-maroon" cx="24" cy="33.4" rx="1.6" ry="2"/>
    </g>
    <g class="rbh-x rbh-x--playful">
      <rect class="rbh-cream" x="7" y="19" width="34" height="20" rx="9"/>
      <path class="rbh-line" d="M14.5 24.2L19.5 27.2L14.5 30.2M33.5 24.2L28.5 27.2L33.5 30.2M20.5 31.6Q24 36 27.5 31.6"/>
    </g>
  @endif
</svg>
