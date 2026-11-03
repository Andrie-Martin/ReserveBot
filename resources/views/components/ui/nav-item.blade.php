@props(['href', 'label', 'mobile' => null, 'tile', 'active' => false, 'count' => 0])
<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class(['rb-nav-item', 'rb-nav-item--active' => $active]) }}>
  <span class="rb-nav-item__tile" aria-hidden="true">{{ $tile }}</span>
  <span class="rb-nav-item__label">{{ $label }}</span>
  @if ($mobile)<span class="rb-nav-item__mobile">{{ $mobile }}</span>@endif
  @if ($count)<span class="rb-nav-item__count">{{ $count }}<span class="sr-only"> waiting</span></span>@endif
</a>
