@props(['href', 'variant' => 'primary', 'size' => 'regular'])
@php
  $variant = in_array($variant, ['primary', 'secondary', 'quiet', 'danger', 'danger-outline'], true) ? $variant : 'primary';
  $size = $size === 'compact' ? 'compact' : 'regular';
@endphp
<a href="{{ $href }}" {{ $attributes->class(['rb-button', 'rb-button--'.$variant, 'rb-button--'.$size]) }}>{{ $slot }}</a>
