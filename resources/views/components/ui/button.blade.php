@props(['variant' => 'primary', 'size' => 'regular', 'type' => 'button'])
@php
  $variant = in_array($variant, ['primary', 'secondary', 'quiet', 'danger', 'danger-outline'], true) ? $variant : 'primary';
  $size = $size === 'compact' ? 'compact' : 'regular';
@endphp
<button type="{{ $type }}" {{ $attributes->class(['rb-button', 'rb-button--'.$variant, 'rb-button--'.$size]) }}>{{ $slot }}</button>
