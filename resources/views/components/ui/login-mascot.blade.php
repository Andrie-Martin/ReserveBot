{{-- The login page's peeking ReserveBot: head, antenna and two hands over an edge (the maroon banner on desktop, the login card on
     phones and tablets). Decorative only: hidden from assistive technology, no pointer events, no focus. It is independent of the
     sidebar mascot (ui/mascot.blade.php and its nine-pose state machine); it only shares that figure's artwork (maroon head, cream
     faceplate, gold antenna, round eyes, small smile) and the .rbm-* fill/stroke classes from css/mascot.css.
     Motion lives in css/login-mascot.css as one slow keyframe loop (no script drives it). Expression layers are hidden by default and
     switched with step keyframes, never cross-faded, so two expressions can never show at once; the first frame is the normal smile.
     Geometry (outer viewBox 22 6 96 98, bottom edge y=104 sits on the edge it peeks over): head 26..114 wide, body drawn down to y=125
     and clipped at 104 by a nested <svg> so the head stays anchored when it tilts; hands are a separate, static layer drawn on top. --}}
<div {{ $attributes->class(['rb-login-mascot']) }} aria-hidden="true">
  <svg class="rb-login-mascot__art" viewBox="22 6 96 98" focusable="false" aria-hidden="true">
    <svg x="-30" y="-40" width="176" height="144" viewBox="-30 -40 176 144">
      <g class="rbl-bob">
        <g class="rbl-tilt">
          <line class="rbm-aerial" x1="70" y1="16" x2="70" y2="41"/>
          <circle class="rbm-gold" cx="70" cy="16" r="8"/>
          <rect class="rbl-head" x="26" y="40" width="88" height="85" rx="25"/>
          <path class="rbl-head-edge" d="M26 125V65a25 25 0 0 1 25-25h38a25 25 0 0 1 25 25v60"/>
          <rect class="rbm-cream" x="37" y="54" width="66" height="39" rx="17"/>

          <g class="rbl-x rbl-x--normal">
            <g class="rbl-blink">
              <circle class="rbm-maroon" cx="55" cy="69" r="5"/>
              <circle class="rbm-maroon" cx="85" cy="69" r="5"/>
            </g>
            <path class="rbm-mouth" d="M61 80Q70 86 79 80"/>
          </g>
          <g class="rbl-x rbl-x--happy">
            <path class="rbm-arc" d="M49.5 71.5Q55 63.5 60.5 71.5M79.5 71.5Q85 63.5 90.5 71.5"/>
            <path class="rbm-mouth" d="M59 79.5Q70 88.5 81 79.5"/>
          </g>
          <g class="rbl-x rbl-x--squint">
            <path class="rbm-arc" d="M50 63.5L60 69L50 74.5M90 63.5L80 69L90 74.5"/>
            <path class="rbm-mouth" d="M60 79Q70 87.5 80 79"/>
          </g>
          <g class="rbl-x rbl-x--curious">
            <circle class="rbm-maroon" cx="55" cy="68" r="6.6"/>
            <circle class="rbl-glint" cx="57.2" cy="65.8" r="2"/>
            <circle class="rbm-maroon" cx="85" cy="70" r="3.6"/>
            <path class="rbm-mouth" d="M64 82Q70 83.5 77 79.5"/>
          </g>
          <g class="rbl-x rbl-x--surprised">
            <circle class="rbm-maroon" cx="55" cy="68.5" r="7"/>
            <circle class="rbm-maroon" cx="85" cy="68.5" r="7"/>
            <circle class="rbl-glint" cx="57.4" cy="66" r="2.3"/>
            <circle class="rbl-glint" cx="87.4" cy="66" r="2.3"/>
            <ellipse class="rbl-mouth-o" cx="70" cy="82.5" rx="3.2" ry="3.8"/>
          </g>

          <g class="rbl-sparks"><path d="M128 40l2.5-8M135 45l6-3.5M137 54l7 .5"/></g>
        </g>
      </g>
    </svg>
    {{-- Hands: static, drawn over the edge so the figure reads as holding on; the accent ring separates them from a maroon banner --}}
    <circle class="rbm-hand-edge rbl-hand" cx="29" cy="102" r="9.4"/>
    <circle class="rbm-hand rbl-hand" cx="29" cy="102" r="7.8"/>
    <circle class="rbm-hand-edge rbl-hand" cx="111" cy="102" r="9.4"/>
    <circle class="rbm-hand rbl-hand" cx="111" cy="102" r="7.8"/>
  </svg>
</div>
