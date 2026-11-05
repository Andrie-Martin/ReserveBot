<section class="rb-availability__calendar-details" aria-labelledby="calendar-events-title">
  <h2 id="calendar-events-title">Event details by date</h2>
  @foreach ($calendar['weeks'] as $week)
    @foreach ($week as $day)
      @if ($day['items'])
        <x-ui.disclosure class="rb-availability__calendar-events" id="events-{{ $day['date']->toDateString() }}">
          <x-slot:summary>{{ count($day['items']) }} {{ count($day['items']) === 1 ? 'event' : 'events' }} on {{ $day['date']->format('F j') }}</x-slot:summary>
          @include('rooms._calendar-events', ['items' => $day['items']])
        </x-ui.disclosure>
      @endif
    @endforeach
  @endforeach
</section>
