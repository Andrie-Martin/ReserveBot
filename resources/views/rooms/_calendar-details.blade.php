@use('App\Support\TimeSlots')
@php
  $eventDays = collect($calendar['weeks'])->flatten(1)->filter(fn ($day) => ! empty($day['items']))->values();
  $selectedDay = $eventDays->first(fn ($day) => $day['isSelected']);
@endphp
<section class="rb-card rb-availability__calendar-details" aria-labelledby="calendar-events-title">
  <h2 id="calendar-events-title">Event details by date</h2>
  <p class="sr-only" role="status" aria-live="polite" data-event-announcement></p>
  {{-- On wide screens this region scrolls inside the card so the card matches the calendar's height; on phones it simply flows.
       Each date with events is its own section, listed in date order. With a script only the chosen date shows (the day picked in
       the calendar by default); without one every date is listed, and the calendar's event links jump to their date. --}}
  <div class="rb-availability__events-scroll" role="region" aria-labelledby="calendar-events-title" tabindex="0">
  <p class="rb-availability__event-prompt" hidden>Select a date with events in the calendar to see them here.</p>
  @if ($eventDays->isEmpty())
    <p class="rb-availability__event-empty">No events this month. Class sessions and bookings you can see will be listed here by date.</p>
  @endif
  @foreach ($eventDays as $day)
    @php $count = count($day['items']); @endphp
    <section class="rb-availability__calendar-events" id="events-{{ $day['date']->toDateString() }}" aria-labelledby="events-{{ $day['date']->toDateString() }}-title">
      <header class="rb-availability__events-head">
        <h3 id="events-{{ $day['date']->toDateString() }}-title" tabindex="-1">{{ $day['date']->format('l, F j') }}</h3>
        <p class="rb-availability__events-count">{{ $count }} {{ $count === 1 ? 'event' : 'events' }}</p>
      </header>
      @include('rooms._calendar-events', ['items' => $day['items']])
    </section>
  @endforeach
  </div>
</section>
