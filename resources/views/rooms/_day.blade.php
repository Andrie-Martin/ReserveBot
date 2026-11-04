@use('App\Support\TimeSlots')
<section class="rb-card rb-availability__day-card" id="day" aria-labelledby="day-title">
  <div class="rb-availability__day-head">
    <div>
      <p class="rb-availability__eyebrow">Rooms on</p>
      <h2 id="day-title">{{ $date->format('l, F j') }}@if ($date->isToday()) <span class="rb-availability__todaytag">Today</span>@endif</h2>
    </div>
    <div class="rb-availability__daynav">
      @if ($prevUrl)
        <x-ui.link-button variant="secondary" class="rb-availability__btn-ghost" :href="$prevUrl">‹ Previous day</x-ui.link-button>
      @else
        <span class="rb-availability__btn-ghost rb-availability__off" aria-disabled="true">‹ Previous day</span>
      @endif
      @if ($nextUrl)
        <x-ui.link-button variant="secondary" class="rb-availability__btn-ghost" :href="$nextUrl">Next day ›</x-ui.link-button>
      @else
        <span class="rb-availability__btn-ghost rb-availability__off" aria-disabled="true">Next day ›</span>
      @endif
    </div>
  </div>

  @if ($dateError)
    <p class="rb-availability__error">{{ $dateError }}</p>
  @endif

  @if (! $isOpen)
    <p class="rb-availability__notice">Rooms aren’t booked on {{ $date->format('l') }}s. Pick another day in the calendar.</p>
  @else
    @unless ($inTerm)
      <p class="rb-availability__notice">This day is outside the semester, so there are no classes. Only bookings are shown.</p>
    @endunless

    {{-- What the viewer has on this day (the calendar only fits three) --}}
    @include('rooms._agenda')

    <form class="rb-availability__finder" method="get" action="{{ route('rooms') }}#day">
      <input type="hidden" name="date" value="{{ $date->toDateString() }}">
      <span class="rb-availability__finder-label">Find a room free from</span>
      <label for="from" class="sr-only">Start time</label>
      <x-ui.select :invalid="(bool) $searchError" aria-describedby="availability-search-feedback" id="from" name="from" required>
        <option value="">Start</option>
        @foreach ($slots as $t)
          <option value="{{ TimeSlots::hhmm($t) }}" @selected($search && $search['from'] === $t)>{{ TimeSlots::clock($t) }}</option>
        @endforeach
      </x-ui.select>
      <span>to</span>
      <label for="to" class="sr-only">End time</label>
      <x-ui.select :invalid="(bool) $searchError" aria-describedby="availability-search-feedback" id="to" name="to" required>
        <option value="">End</option>
        @foreach ($slots as $t)
          <option value="{{ TimeSlots::hhmm($t + $step) }}" @selected($search && $search['to'] === $t + $step)>{{ TimeSlots::clock($t + $step) }}</option>
        @endforeach
      </x-ui.select>
      <x-ui.button type="submit" class="rb-availability__btn-small">Find rooms</x-ui.button>
      @if ($search || $searchError)
        <x-ui.link-button variant="secondary" class="rb-availability__quiet" :href="route('rooms', ['date' => $date->toDateString()]).'#day'">Clear</x-ui.link-button>
      @endif
    </form>

    <div id="availability-search-feedback">
    @if ($searchError)
      <p class="rb-availability__error">{{ $searchError }}</p>
    @endif

    @if ($search)
      @php $found = count($search['free']); @endphp
      <div @class(['rb-availability__results', 'rb-availability__none-free' => $found === 0]) role="status">
        @if ($found > 0)
          <p><strong>{{ $found }} {{ $found === 1 ? 'room is' : 'rooms are' }} free</strong> from {{ TimeSlots::range($search['from'], $search['to']) }}:
            {{ collect($search['free'])->pluck('name')->join(', ', ' and ') }}.</p>
        @else
          <p><strong>No room is free</strong> from {{ TimeSlots::range($search['from'], $search['to']) }}. Try a shorter time or another day.</p>
        @endif
        @if ($search['started'])
          <p class="rb-availability__small">Part of that time has already passed today.</p>
        @endif
      </div>
    @endif

    </div>
    <h3 class="rb-availability__list-title">Free times in each room</h3>
    @if ($now !== null && $slots && $now > $slots[0])
      <p class="rb-availability__muted rb-availability__small">Times that have already started today aren’t shown as free.</p>
    @endif

    <ul class="rb-availability__roomlist">
      @forelse ($roomList as $row)
        @include('rooms._room')
      @empty
        <li><p class="rb-availability__empty">No rooms listed for this department.</p></li>
      @endforelse
    </ul>
  @endif
</section>
