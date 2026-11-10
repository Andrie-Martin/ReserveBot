{{--
  Compact status row for NON-critical state (email connected / not connected yet). One icon, a short
  title, an optional message, and an optional action on the same row (wraps under the text on narrow screens).
  Use x-ui.alert for errors, destructive warnings and anything the user must not miss.
  Props: tone (success|warning|info), role (status|alert|null), title, heading (render the title as an <h2>).
  Slots: default = message, actions = the control that resolves the state (a form/link/button).
--}}
@props(['tone' => 'info', 'role' => null, 'title' => null, 'heading' => false])
@php
  $tone = in_array($tone, ['info', 'success', 'warning'], true) ? $tone : 'info';
  $titleTag = $heading ? 'h2' : 'p';
@endphp
<div {{ $attributes->class(['rb-status', 'rb-tone-'.$tone]) }} @if ($role) role="{{ $role }}" @endif>
  <span class="rb-status__icon" aria-hidden="true">
    @if ($tone === 'success')
      <svg viewBox="0 0 16 16" focusable="false"><path d="M3.5 8.5l3 3 6-7"/></svg>
    @elseif ($tone === 'warning')
      <svg viewBox="0 0 16 16" focusable="false"><path d="M8 3.5v5.25M8 11.75v.01"/></svg>
    @else
      <svg viewBox="0 0 16 16" focusable="false"><path d="M8 7.25v4.5M8 4.25v.01"/></svg>
    @endif
  </span>
  <div class="rb-status__body">
    @if ($title)<{{ $titleTag }} class="rb-status__title">{{ $title }}</{{ $titleTag }}>@endif
    {{ $slot }}
  </div>
  @isset($actions)
    <div class="rb-status__actions">{{ $actions }}</div>
  @endisset
</div>
