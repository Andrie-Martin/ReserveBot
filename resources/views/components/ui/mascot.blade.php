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
     and (109,80), arm length 32, feet on y=142. Props sit in front of the belly (x 44..96, y 96..133); the door is held a little to the right (x 72..100). --}}
<div class="rb-mascot" data-rb-mascot data-pose="{{ $pose }}" aria-hidden="true">
  <div class="rb-mascot__stage">
    <svg class="rb-mascot__art" viewBox="0 6 200 140" width="200" height="140" focusable="false" aria-hidden="true">
      <g transform="translate(30 0)">
        <ellipse class="rbm-shadow" cx="70" cy="143" rx="36" ry="2.6"/>

        {{-- Arms are drawn first so the body overlaps their roots; each pivots on its shoulder --}}
        <g class="rbm-arm rbm-arm--l">
          <line class="rbm-limb" x1="31" y1="80" x2="31" y2="112"/>
          <circle class="rbm-hand-ball" cx="31" cy="115" r="9"/>
        </g>
        <g class="rbm-arm rbm-arm--r">
          <line class="rbm-limb" x1="109" y1="80" x2="109" y2="112"/>
        </g>

        <rect class="rbm-maroon" x="47" y="116" width="16" height="26" rx="8"/>
        <rect class="rbm-maroon" x="77" y="116" width="16" height="26" rx="8"/>
        <rect class="rbm-maroon" x="26" y="40" width="88" height="83" rx="25"/>
        <rect class="rbm-gold rbm-pill" x="56" y="104" width="28" height="9" rx="4.5"/>

        <rect class="rbm-cream" x="37" y="54" width="66" height="39" rx="17"/>
        <g class="rbm-eyes">
          <circle class="rbm-maroon" cx="55" cy="69" r="5"/>
          <circle class="rbm-maroon" cx="85" cy="69" r="5"/>
        </g>
        <path class="rbm-mouth" d="M61 80Q70 86 79 80"/>

        <line class="rbm-aerial" x1="70" y1="16" x2="70" y2="41"/>
        <circle class="rbm-gold" cx="70" cy="16" r="8"/>

        {{-- Held props (in front of the belly). The door is held by its right edge: the arm hangs down to it and a hand drawn on top grips the frame. --}}
        <g class="rbm-prop rbm-prop--door">
          <rect class="rbm-cream rbm-gold-edge" x="72" y="92" width="28" height="42" rx="4"/>
          <rect class="rbm-maroon" x="78" y="99" width="16" height="15" rx="2.5"/>
          <circle class="rbm-gold" cx="77" cy="121" r="2.3"/>
        </g>
        {{-- The hand that grips the door: the right arm's tip, repeated in front of the door and turning with the arm (same angle variable), so it never detaches --}}
        <g class="rbm-arm rbm-arm--r rbm-grip"><circle class="rbm-maroon" cx="109" cy="112" r="6.5"/></g>
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
          <rect class="rbm-cream" x="44" y="96" width="52" height="37" rx="7"/>
          <circle class="rbm-maroon" cx="58" cy="113" r="5"/>
          <path class="rbm-maroon" d="M50.5 128a7.5 6.5 0 0 1 15 0z"/>
          <path class="rbm-line" d="M72 109h15M72 115h15M72 121h10"/>
          <rect class="rbm-frame" x="44" y="96" width="52" height="37" rx="7"/>
          <rect class="rbm-gold" x="62" y="93" width="16" height="6" rx="3"/>
        </g>
        <g class="rbm-prop rbm-prop--key">
          <circle class="rbm-keyring" cx="55" cy="112" r="7"/>
          <path class="rbm-keyshaft" d="M62 112H91M83 112v8M90 112v6"/>
        </g>
        <g class="rbm-prop rbm-prop--tool">
          <g transform="translate(70 113) rotate(40)">
            <rect class="rbm-cream" x="-3" y="-6" width="6" height="27" rx="3"/>
            <circle class="rbm-cream" cx="0" cy="-10" r="8.5"/>
            <rect class="rbm-maroon" x="-3.2" y="-21" width="6.4" height="11"/>
          </g>
        </g>
      </g>
    </svg>
  </div>
</div>
