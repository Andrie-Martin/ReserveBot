@use('App\Support\TimeSlots')
@php
  // One selected date drives the calendar highlight, the agenda and this panel; it comes from the server (?date=), so it
  // survives refresh, Back/Forward and direct links with no script involved.
  $selectedDay = collect($calendar['weeks'])->flatten(1)->first(fn ($day) => $day['isSelected']);
  $items = $selectedDay['items'] ?? [];
  $count = count($items);
@endphp
<section class="rb-card rb-availability__calendar-details" aria-labelledby="calendar-events-title">
  <h2 id="calendar-events-title">Event details</h2>
  <div class="rb-availability__events-scroll" role="region" aria-labelledby="calendar-events-title" tabindex="0">
    <section class="rb-availability__calendar-events" aria-labelledby="events-selected-title">
      <header class="rb-availability__events-head">
        <h3 id="events-selected-title">{{ $selectedDay['date']->format('l, F j') }}</h3>
        <p class="rb-availability__events-count">{{ $count }} {{ $count === 1 ? 'event' : 'events' }}</p>
      </header>
      @if ($count)
        @include('rooms._calendar-events', ['items' => $items])
      @else
        <p class="rb-availability__event-empty">No events on this day. Class sessions and bookings you can see will be listed here.</p>
      @endif
    </section>
  </div>
</section>
