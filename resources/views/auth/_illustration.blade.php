{{-- Decorative geometry inspired by the committed login export; not an official building or seal. --}}
<svg class="rb-auth-illustration" viewBox="0 0 520 680" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
  <rect class="rb-auth-illustration__arch" x="26" y="10" width="468" height="650" rx="210" />
  <text class="rb-auth-illustration__caption" x="88" y="56">PURPOSE-BUILT FOR CEA</text>
  <rect class="rb-auth-illustration__building" x="65" y="107" width="392" height="480" rx="6" />
  <rect class="rb-auth-illustration__white" x="84" y="184" width="350" height="399" />
  <path class="rb-auth-illustration__gold" d="M84 183h350v20H84z M63 581h404v4H63z" />
  @foreach ([226, 299, 372, 445] as $y)
    @foreach ([125, 181, 237, 293, 349] as $x)
      <rect class="rb-auth-illustration__window" x="{{ $x }}" y="{{ $y }}" width="34" height="47" rx="3" />
    @endforeach
  @endforeach
  <rect class="rb-auth-illustration__base" x="54" y="585" width="421" height="75" rx="12" />
  <text class="rb-auth-illustration__motto" x="264" y="625" text-anchor="middle">SPACE TO LEARN. TO BUILD. TO CREATE.</text>
</svg>
