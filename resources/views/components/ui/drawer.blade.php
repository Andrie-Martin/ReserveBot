@props(['id', 'title', 'kicker' => null, 'sub' => null, 'closeTo' => '#'])
{{-- A right-hand drawer on wide screens, a full-screen panel on phones. Native <dialog> gives Escape, focus containment and
     focus return; the ✕ link closes it without script as well (the panel opens by :target when script is unavailable). --}}
<dialog id="{{ $id }}" {{ $attributes->class(['rb-drawer']) }} aria-labelledby="{{ $id }}-title">
  <div class="rb-drawer__head">
    <div>
      @if ($kicker)<p class="rb-drawer__kicker">{{ $kicker }}</p>@endif
      <h2 class="rb-drawer__title" id="{{ $id }}-title">{{ $title }}</h2>
      @if ($sub)<p class="rb-drawer__sub">{{ $sub }}</p>@endif
    </div>
    <a class="rb-button rb-button--secondary rb-button--compact rb-drawer__close" href="{{ $closeTo }}" data-rb-drawer-close>Close<span class="sr-only"> {{ $title }}</span></a>
  </div>
  <div class="rb-drawer__body">{{ $slot }}</div>
</dialog>
