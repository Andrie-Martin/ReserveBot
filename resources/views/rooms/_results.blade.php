<section class="rb-availability__results-section" aria-labelledby="room-results-title">
  @include('rooms._search')
    @php
      $axisValid = is_array($slots) && count($slots) > 0 && is_int($step) && $step > 0;
      $axisStart = $axisValid ? reset($slots) : null;
      $axisEnd = $axisValid && is_int(end($slots)) ? end($slots) + $step : null;
      foreach (is_array($slots) ? array_values($slots) : [] as $i => $slot) {
        if (! is_int($slot) || ! is_int($axisStart) || $slot !== $axisStart + $i * $step) $axisValid = false;
      }
      $axisValid = $axisValid && is_int($axisStart) && is_int($axisEnd) && $axisStart >= 0 && $axisEnd <= 1440 && $axisEnd > $axisStart;
      $ticks = $axisValid ? array_unique([0, (int) ceil(count($slots) / 4), (int) ceil(count($slots) / 2), (int) ceil(3 * count($slots) / 4), count($slots)]) : [];
    @endphp
    <div class="rb-availability__section-head">
      <h3 class="rb-availability__list-title" id="room-results-title">Free times in each room</h3>
      @if ($now !== null && $slots && $now > $slots[0])
        <p class="rb-availability__muted rb-availability__small">Times that have already started today aren’t shown as free.</p>
      @endif
      @if ($axisValid)
        <p class="rb-availability__timeline-legend">Green: reported free. Neutral: not reported as free. Booking rules and final validation still apply.</p>
      @endif
    </div>
    <div @class(['rb-availability__board' => $axisValid]) @if ($axisValid) role="region" aria-label="Room availability timeline; scroll horizontally for all times and actions" tabindex="0" @endif>
    @if ($axisValid)
      <div class="rb-availability__axis">
        <strong>Room</strong>
        <div><strong>Selectable time window</strong><div class="rb-availability__ticks">
          @foreach ($ticks as $tick)
            <span style="left: {{ 100 * $tick / count($slots) }}%">{{ \App\Support\TimeSlots::clock($axisStart + $tick * $step) }}</span>
          @endforeach
        </div></div>
      </div>
    @endif
    <ul class="rb-availability__roomlist">
      @forelse ($roomList as $row)
        @include('rooms._room')
      @empty
        <li><p class="rb-availability__empty">No rooms listed for this department.</p></li>
      @endforelse
    </ul>
    </div>
</section>
