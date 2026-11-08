<section class="rb-card rb-availability__calendar-details" aria-labelledby="calendar-events-title">
  <h2 id="calendar-events-title">Event details by date</h2>
  <p class="sr-only" role="status" aria-live="polite" data-event-announcement></p>
  @php $hasEvents = collect($calendar['weeks'])->flatten(1)->contains(fn ($day) => ! empty($day['items'])); @endphp
  {{-- On wide screens this region scrolls inside the card so the card matches the calendar's height; on phones it simply flows. --}}
  <div class="rb-availability__events-scroll" role="region" aria-labelledby="calendar-events-title" tabindex="0">
  <p class="rb-availability__event-prompt" hidden>Select an event count in the calendar to view its details here.</p>
  @unless ($hasEvents)
    <p class="rb-availability__event-empty">No events this month. Class sessions and bookings you can see will be listed here by date.</p>
  @endunless
  @foreach ($calendar['weeks'] as $week)
    @foreach ($week as $day)
      @if ($day['items'])
        <x-ui.disclosure :open="true" class="rb-availability__calendar-events" id="events-{{ $day['date']->toDateString() }}">
          <x-slot:summary>{{ count($day['items']) }} {{ count($day['items']) === 1 ? 'event' : 'events' }} on {{ $day['date']->format('F j') }}</x-slot:summary>
          @include('rooms._calendar-events', ['items' => $day['items']])
        </x-ui.disclosure>
      @endif
    @endforeach
  @endforeach
  </div>
</section>
