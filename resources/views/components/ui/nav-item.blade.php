@props(['href', 'label', 'mobile' => null, 'icon', 'active' => false, 'count' => 0])
<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class(['rb-nav-item', 'rb-nav-item--active' => $active]) }}>
  <x-ui.icon :name="$icon" class="rb-nav-item__icon" />
  <span class="rb-nav-item__label">{{ $label }}</span>
  @if ($mobile)<span class="rb-nav-item__mobile">{{ $mobile }}</span>@endif
  @if ($count)<span class="rb-nav-item__count">{{ $count }}<span class="sr-only"> waiting</span></span>@endif
</a>
