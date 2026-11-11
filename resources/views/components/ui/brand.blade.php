@props(['href', 'animated' => false])
<a href="{{ $href }}" {{ $attributes->class(['rb-brand']) }}>
  <x-ui.mascot-head class="rb-brand__logo" :animated="$animated" />
  <span class="rb-brand__words"><span class="rb-brand__name">ReserveBot</span><span class="rb-brand__college">PUP College of Engineering</span></span>
</a>
