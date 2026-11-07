@props(['bag'])
@if ($bag->any())
  <div {{ $attributes->class(['rb-error-summary']) }}>
    @include('manage._errors', ['bag' => $bag])
  </div>
@endif
