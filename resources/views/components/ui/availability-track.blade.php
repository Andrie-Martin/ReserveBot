@props(['ranges', 'start', 'end', 'blocked' => false])
@php
  $valid = is_int($start) && is_int($end) && $end > $start && is_array($ranges);
  $previous = $start;
  foreach (is_array($ranges) ? $ranges : [] as $range) {
    if (! is_array($range) || count($range) !== 2 || ! isset($range[0], $range[1])
        || ! is_int($range[0]) || ! is_int($range[1]) || $range[0] < $start
        || $range[1] > $end || $range[1] <= $range[0] || $range[0] < $previous) {
      $valid = false;
      break;
    }
    $previous = $range[1];
  }
@endphp
@if ($valid)
  <div {{ $attributes->class(['rb-availability-track', 'rb-availability-track--blocked' => $blocked]) }} aria-hidden="true" data-axis-start="{{ $start }}" data-axis-end="{{ $end }}">
    @foreach ($ranges as [$from, $to])
      <span class="rb-availability-track__free" data-from="{{ $from }}" data-to="{{ $to }}" style="left: {{ 100 * ($from - $start) / ($end - $start) }}%; width: {{ 100 * ($to - $from) / ($end - $start) }}%"></span>
    @endforeach
  </div>
@endif
