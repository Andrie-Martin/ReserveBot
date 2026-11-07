@props(['title', 'description', 'level' => 2])
@php $level = in_array((int) $level, [2, 3, 4, 5, 6], true) ? (int) $level : 2; @endphp
<div {{ $attributes->class(['rb-empty-state']) }}>
  <h{{ $level }} class="rb-heading rb-heading--h2">{{ $title }}</h{{ $level }}>
  <p class="rb-description">{{ $description }}</p>
  @isset($actions)<div class="rb-actions">{{ $actions }}</div>@endisset
</div>
