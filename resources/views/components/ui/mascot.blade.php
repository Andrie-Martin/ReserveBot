@props(['pose' => 'idle'])
{{-- ReserveBot's sidebar mascot: purely decorative, no behaviour of its own. The character comes from docs/frontend/ReserveBot-Mascot.jpg
     (rounded maroon head-and-body, cream faceplate, two round eyes and a small smile, short antenna with a gold tip, capsule arms,
     stubby legs, gold chest pill) and the nine pose compositions from docs/frontend/ReserveBot-Mascot-Poses.png (without that
     sheet's RB belly logo or its changed proportions). Every pose is that same figure; a pose only changes the arm angles, which
     hands show, a prop, the eyes (round, or happy arcs) and a few gold sparks. css/mascot.css does that from data-pose. The
     initial pose is the current page's (passed in by the navigation partial, which owns the page-to-pose mapping);
     public/js/mascot.js only swaps data-pose when a sidebar link is hovered or keyboard-focused.
     Poses: idle, attend (welcome), wave, door, calendar, clipboard, key, tool, badge. Hidden from assistive technology, never
     focusable, never announced.
     Geometry (viewBox 200x140, character centred on x=100 via the translate): body 26..114 x 40..123, shoulders (31,80) and
     (109,80), arm length 32, feet on y=142.
     One holding system. Limbs are drawn over the body but under the faceplate (maroon on maroon is invisible, so idle still reads
     like the reference's capsule arms), and every arm ends in a hand (.rbm-hand, r 6.8). The hand is a second group with the same
     classes and angle variable as the limb, drawn after the props, so it rotates with the arm (a grip cannot detach) and sits on
     the object's edge. Two-handed (calendar, clipboard, door: one shared footprint x 44..96): both hands on the side edges. One-handed
     (key, tool, badge): the LEFT hand carries, always at the anchor (46.2,108.1), and key and wrench share one axis through it
     (head/ring centre (30,88), direction (0.627, 0.779)). Tip = shoulder + 32 * (-sin a, cos a); the angles live in css/mascot.css. --}}
<div class="rb-mascot" data-rb-mascot data-pose="{{ $pose }}" aria-hidden="true">
  <div class="rb-mascot__stage">
    <svg class="rb-mascot__art" viewBox="0 6 200 140" width="200" height="140" focusable="false" aria-hidden="true">
      <g transform="translate(30 0)">
        <ellipse class="rbm-shadow" cx="70" cy="143" rx="36" ry="2.6"/>

        <rect class="rbm-maroon" x="47" y="116" width="16" height="26" rx="8"/>
        <rect class="rbm-maroon" x="77" y="116" width="16" height="26" rx="8"/>
        <rect class="rbm-maroon" x="26" y="40" width="88" height="83" rx="25"/>
        <rect class="rbm-gold rbm-pill" x="56" y="104" width="28" height="9" rx="4.5"/>

        {{-- Limbs: over the body but under the faceplate, so an arm angled across the chest never cuts into the face. The accent edge
             only shows in holding poses, where an arm lies over the (same-coloured) body and would otherwise vanish. --}}
        <g class="rbm-arm rbm-arm--l">
          <line class="rbm-limb-edge" x1="31" y1="80" x2="31" y2="112"/>
          <line class="rbm-limb" x1="31" y1="80" x2="31" y2="112"/>
        </g>
        <g class="rbm-arm rbm-arm--r">
          <line class="rbm-limb-edge" x1="109" y1="80" x2="109" y2="112"/>
          <line class="rbm-limb" x1="109" y1="80" x2="109" y2="112"/>
        </g>

        <rect class="rbm-cream" x="37" y="54" width="66" height="39" rx="17"/>
        <g class="rbm-eyes rbm-eyes--open">
          <circle class="rbm-maroon" cx="55" cy="69" r="5"/>
          <circle class="rbm-maroon" cx="85" cy="69" r="5"/>
        </g>
        <g class="rbm-eyes rbm-eyes--happy">
          <path class="rbm-arc" d="M49.5 71.5Q55 63.5 60.5 71.5M79.5 71.5Q85 63.5 90.5 71.5"/>
        </g>
        <path class="rbm-mouth" d="M61 80Q70 86 79 80"/>

        <line class="rbm-aerial" x1="70" y1="16" x2="70" y2="41"/>
        <circle class="rbm-gold" cx="70" cy="16" r="8"/>

        {{-- Gold sparks of the greeting poses: right side for wave, both sides for attend --}}
        <g class="rbm-sparks rbm-sparks--r"><path d="M128 40l2.5-8M135 45l6-3.5M137 54l7 .5"/></g>
        <g class="rbm-sparks rbm-sparks--l" transform="translate(140 0) scale(-1 1)"><path d="M128 40l2.5-8M135 45l6-3.5M137 54l7 .5"/></g>

        {{-- Held objects: centred on the torso (two-handed) or on the left-hand axis (one-handed) --}}
        <g class="rbm-prop rbm-prop--door">
          <rect class="rbm-gold" x="44" y="94" width="52" height="44" rx="6"/>
          <rect class="rbm-maroon" x="48.5" y="98.5" width="43" height="35.5" rx="3"/>
          <path class="rbm-panel" d="M52.5 102.5h14v26h-14zM73.5 102.5h14v26h-14z"/>
          <circle class="rbm-gold" cx="68" cy="117" r="2"/>
          <circle class="rbm-gold" cx="72" cy="117" r="2"/>
        </g>
        <g class="rbm-prop rbm-prop--calendar">
          <rect class="rbm-cream" x="44" y="96" width="52" height="37" rx="7"/>
          <path class="rbm-gold" d="M44 103a7 7 0 0 1 7-7h38a7 7 0 0 1 7 7v3H44z"/>
          <rect class="rbm-gold" x="55" y="92.5" width="4.5" height="8" rx="2.25"/>
          <rect class="rbm-gold" x="80.5" y="92.5" width="4.5" height="8" rx="2.25"/>
          <path class="rbm-cells" d="M50 111h6v5h-6zM59 111h6v5h-6zM68 111h6v5h-6zM77 111h6v5h-6zM50 119h6v5h-6zM59 119h6v5h-6zM68 119h6v5h-6zM77 119h6v5h-6z"/>
          <rect class="rbm-maroon" x="86" y="119" width="6" height="5" rx="1"/>
        </g>
        <g class="rbm-prop rbm-prop--clipboard">
          <rect class="rbm-cream" x="44" y="96" width="52" height="37" rx="7"/>
          <path class="rbm-tick" d="M52 108.5l2.2 2.4 4-4.6M52 116.5l2.2 2.4 4-4.6M52 124.5l2.2 2.4 4-4.6"/>
          <path class="rbm-line" d="M63 108.5h24M63 116.5h24M63 124.5h17"/>
          <rect class="rbm-gold" x="60" y="92" width="20" height="8" rx="3"/>
        </g>
        <g class="rbm-prop rbm-prop--badge">
          <rect class="rbm-cream" x="44" y="99" width="40" height="30" rx="5"/>
          <rect class="rbm-gold" x="57" y="95.5" width="14" height="6" rx="3"/>
          <circle class="rbm-maroon" cx="59.5" cy="111" r="4.2"/>
          <path class="rbm-maroon" d="M53 124a6.5 5.5 0 0 1 13 0z"/>
          <path class="rbm-line" d="M69.5 110h10M69.5 116h10"/>
        </g>
        <g class="rbm-prop rbm-prop--key">
          <circle class="rbm-keyring" cx="30" cy="88" r="7.6"/>
          <path class="rbm-keyshaft" d="M35.6 95L57.6 122.3M51.3 114.5l4.7-3.8M55.1 119.2l4.7-3.8"/>
        </g>
        <g class="rbm-prop rbm-prop--tool">
          <g transform="translate(38.15 98.13) rotate(-38.8) scale(1.3)">
            <rect class="rbm-cream" x="-3" y="-6" width="6" height="27" rx="3"/>
            <circle class="rbm-cream" cx="0" cy="-10" r="8.5"/>
            <rect class="rbm-maroon" x="-3.2" y="-21" width="6.4" height="11"/>
          </g>
        </g>

        {{-- Hands: the tip of each arm, repeated here so it is drawn ON TOP of the held object. Same group classes and the same
             angle variable as the limb, so limb and hand turn as one and a grip cannot detach. --}}
        <g class="rbm-arm rbm-arm--l rbm-hands">
          <circle class="rbm-hand-edge" cx="31" cy="112" r="8.4"/>
          <circle class="rbm-hand" cx="31" cy="112" r="6.8"/>
        </g>
        <g class="rbm-arm rbm-arm--r rbm-hands">
          <circle class="rbm-hand-edge" cx="109" cy="112" r="8.4"/>
          <circle class="rbm-hand" cx="109" cy="112" r="6.8"/>
        </g>
      </g>
    </svg>
  </div>
</div>
