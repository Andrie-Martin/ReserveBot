@use('App\Support\TimeSlots')
    <form class="rb-availability__finder" method="get" action="{{ route('rooms') }}#day">
      <input type="hidden" name="date" value="{{ $date->toDateString() }}">
      <div class="rb-availability__finder-field">
        <span class="rb-availability__finder-label">Find a room free from</span>
        <label for="from" class="sr-only">Start time</label>
        <x-ui.select :invalid="(bool) $searchError" aria-describedby="availability-search-feedback" id="from" name="from" required>
          <option value="">Start</option>
          @foreach ($slots as $t)
            <option value="{{ TimeSlots::hhmm($t) }}" @selected($search && $search['from'] === $t)>{{ TimeSlots::clock($t) }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="rb-availability__finder-field">
        <span class="rb-availability__finder-label rb-availability__finder-to">to</span>
        <label for="to" class="sr-only">End time</label>
        <x-ui.select :invalid="(bool) $searchError" aria-describedby="availability-search-feedback" id="to" name="to" required>
          <option value="">End</option>
          @foreach ($slots as $t)
            <option value="{{ TimeSlots::hhmm($t + $step) }}" @selected($search && $search['to'] === $t + $step)>{{ TimeSlots::clock($t + $step) }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="rb-availability__finder-actions">
        <x-ui.button type="submit" class="rb-availability__btn-small">Find rooms</x-ui.button>
        @if ($search || $searchError)
          <x-ui.link-button variant="secondary" class="rb-availability__quiet" :href="route('rooms', ['date' => $date->toDateString()]).'#day'">Clear</x-ui.link-button>
        @endif
      </div>
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
