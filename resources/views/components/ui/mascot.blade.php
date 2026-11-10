@props(['pose' => 'idle'])
{{-- ReserveBot's sidebar mascot: purely decorative, no behaviour of its own. Built from docs/frontend/ReserveBot-Mascot.jpg:
     one rounded maroon head-and-body, a cream faceplate with two round eyes and a small smile, a short antenna with a gold
     tip, capsule arms, stubby legs and a gold chest pill. Every pose is that same figure; a pose only changes the arm
     angles, a small prop, and (barely) where the eyes look. css/mascot.css does all of that from data-pose. The initial pose
     is the current page's (passed in by the navigation partial, which owns the page-to-pose mapping); public/js/mascot.js
     only swaps data-pose when a sidebar link is hovered or keyboard-focused.
     Poses: idle, attend (neutral), wave, door, calendar, clipboard, key, tool, badge. Hidden from assistive technology,
     never focusable, never announced.
     Geometry (viewBox 200x140, character centred on x=100 via the translate): body 26..114 x 40..123, shoulders (31,80)
     and (109,80), arm length 32, feet on y=142.
     One holding system. Limbs are drawn over the body but under the faceplate (maroon on maroon is invisible, so idle still reads as
     the reference's arms behind the body), and every arm ends in a hand (.rbm-hand, r 6.8). The hand is a second group with the same classes and
     angle variable as the limb, drawn after the prop, so it rotates with the arm (a grip cannot detach) and sits on the object's edge.
     Two-handed (large objects: calendar, clipboard, door): the object is centred on the torso and both hands sit on its side
     edges (hand centres are the arm tips at the pose angle, e.g. calendar 23.4deg inward puts them on x=44 and x=96).
     One-handed (small objects: key, tool, badge): the right hand is the carrier, always at the same anchor (94,108) (arm
     28.4deg), the object passes through or ends at that anchor; the left arm rests at its idle angle.
     The pose angles live in css/mascot.css and were derived from tip = shoulder + 32 * (-sin a, cos a). --}}
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
          <circle class="rbm-hand-ball" cx="31" cy="115" r="9"/>
        </g>
        <g class="rbm-arm rbm-arm--r">
          <line class="rbm-limb-edge" x1="109" y1="80" x2="109" y2="112"/>
          <line class="rbm-limb" x1="109" y1="80" x2="109" y2="112"/>
        </g>

        <rect class="rbm-cream" x="37" y="54" width="66" height="39" rx="17"/>
        <g class="rbm-eyes">
          <circle class="rbm-maroon" cx="55" cy="69" r="5"/>
          <circle class="rbm-maroon" cx="85" cy="69" r="5"/>
        </g>
        <path class="rbm-mouth" d="M61 80Q70 86 79 80"/>

        <line class="rbm-aerial" x1="70" y1="16" x2="70" y2="41"/>
        <circle class="rbm-gold" cx="70" cy="16" r="8"/>

        {{-- Held objects, centred on the torso (two-handed) or around the right-hand anchor (94,108) (one-handed) --}}
        <g class="rbm-prop rbm-prop--door">
          <rect class="rbm-cream rbm-gold-edge" x="54" y="94" width="32" height="44" rx="4"/>
          <rect class="rbm-maroon" x="60" y="105" width="20" height="14" rx="2.5"/>
          <rect class="rbm-maroon" x="60" y="123" width="20" height="10" rx="2.5"/>
          <circle class="rbm-gold" cx="81" cy="128" r="2.4"/>
        </g>
        <g class="rbm-prop rbm-prop--calendar">
          <rect class="rbm-cream" x="44" y="96" width="52" height="37" rx="7"/>
          <rect class="rbm-maroon" x="45.5" y="106" width="49" height="5.5"/>
          <circle class="rbm-maroon" cx="60" cy="123" r="3"/>
          <circle class="rbm-maroon" cx="80" cy="123" r="3"/>
          <rect class="rbm-frame" x="44" y="96" width="52" height="37" rx="7"/>
        </g>
        <g class="rbm-prop rbm-prop--clipboard">
          <rect class="rbm-cream" x="44" y="96" width="52" height="37" rx="7"/>
          <path class="rbm-line" d="M55 109h30M55 116h30M55 123h18"/>
          <rect class="rbm-frame" x="44" y="96" width="52" height="37" rx="7"/>
          <rect class="rbm-gold" x="60" y="92" width="20" height="8" rx="3"/>
        </g>
        <g class="rbm-prop rbm-prop--badge">
          <rect class="rbm-cream rbm-gold-edge" x="68" y="97" width="26" height="35" rx="5"/>
          <rect class="rbm-gold" x="76" y="94.5" width="10" height="5" rx="2.5"/>
          <circle class="rbm-maroon" cx="81" cy="111" r="5"/>
          <path class="rbm-maroon" d="M72.5 127a8.5 7 0 0 1 17 0z"/>
        </g>
        <g class="rbm-prop rbm-prop--key">
          <circle class="rbm-keyring" cx="59" cy="104.5" r="7"/>
          <path class="rbm-keyshaft" d="M66 105.2L104 109.5M99 109v7M104 109.5v5"/>
        </g>
        <g class="rbm-prop rbm-prop--tool">
          <g transform="translate(81.7 105.3) rotate(-76.8)">
            <rect class="rbm-cream" x="-3" y="-6" width="6" height="27" rx="3"/>
            <circle class="rbm-cream" cx="0" cy="-10" r="8.5"/>
            <rect class="rbm-maroon" x="-3.2" y="-21" width="6.4" height="11"/>
          </g>
        </g>

        {{-- Hands: the tip of each arm, repeated here so it is drawn ON TOP of the held object. Same group classes and the same
             angle variable as the limb, so limb and hand turn as one and a grip cannot detach. Shown only in holding poses. --}}
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
