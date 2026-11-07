@props(['tone' => 'info', 'role' => null])
@php $tone = in_array($tone, ['info', 'success', 'warning', 'danger'], true) ? $tone : 'info'; @endphp
<div {{ $attributes->class(['rb-alert', 'rb-tone-'.$tone]) }} @if ($role) role="{{ $role }}" @endif>{{ $slot }}</div>
