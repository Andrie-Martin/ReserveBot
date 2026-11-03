@props(['label', 'tone' => 'neutral'])
@php $tone = in_array($tone, ['neutral', 'success', 'warning', 'danger'], true) ? $tone : 'neutral'; @endphp
<span {{ $attributes->class(['rb-badge', 'rb-tone-'.$tone]) }}>{{ $label }}</span>
