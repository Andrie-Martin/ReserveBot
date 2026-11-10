@use('App\Support\TimeSlots')
<section class="rb-card rb-availability__cal-card" aria-labelledby="cal-title">
  <div class="rb-availability__cal-bar">
    <div class="rb-availability__cal-heading">
      <h2 id="cal-title" class="rb-availability__cal-title">{{ $calendar['title'] }}</h2>
      <p class="rb-availability__cal-hint">You can look from today up to {{ $lastDay->format('F j') }}.</p>
    </div>
    {{-- Month arrows appear only when the neighbouring month has a day you can pick (inside the booking window and on an
         open day). Where there is nothing to go to, the arrow is left out instead of shown as a dead control. --}}
    <div class="rb-availability__cal-nav" role="group" aria-label="Change month">
      @if ($calendar['prevUrl'])
        <x-ui.link-button variant="secondary" class="rb-availability__cal-arrow" :href="$calendar['prevUrl']" :aria-label="'Previous month, '.($calendar['prevTitle'] ?? 'earlier')" :title="'Previous month, '.($calendar['prevTitle'] ?? 'earlier')"><span aria-hidden="true">‹</span></x-ui.link-button>
      @endif
      <x-ui.link-button variant="secondary" class="rb-availability__btn-ghost" :href="$todayUrl" aria-label="Go to today">Today</x-ui.link-button>
      @if ($calendar['nextUrl'])
        <x-ui.link-button variant="secondary" class="rb-availability__cal-arrow" :href="$calendar['nextUrl']" :aria-label="'Next month, '.($calendar['nextTitle'] ?? 'later')" :title="'Next month, '.($calendar['nextTitle'] ?? 'later')"><span aria-hidden="true">›</span></x-ui.link-button>
      @endif
    </div>
  </div>

  <div class="rb-availability__cal-wrap" role="region" aria-label="Room availability calendar; scroll horizontally on small screens" tabindex="0">
    <table class="rb-availability__calendar">
      <thead>
        <tr>
          @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $weekday)
            <th scope="col"><span class="rb-availability__long">{{ $weekday }}</span><span class="rb-availability__short" aria-hidden="true">{{ substr($weekday, 0, 3) }}</span></th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach ($calendar['weeks'] as $week)
          <tr>
            @foreach ($week as $day)
              @include('rooms._calendar-day')
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <ul class="rb-availability__legend" aria-label="What the colours mean">
    @if ($staffView)
      <li><span class="rb-availability__sw rb-availability__sw-booking"></span>Booking by your department</li>
    @else
      <li><span class="rb-availability__sw rb-availability__sw-class"></span>Your block’s class</li>
      <li><span class="rb-availability__sw rb-availability__sw-booking"></span>Your booking</li>
      <li><span class="rb-availability__ok">✓</span>Booked for the class</li>
    @endif
    <li><span class="rb-availability__sw rb-availability__sw-pending"></span>Waiting for approval</li>
    <li><span class="rb-availability__sw rb-availability__sw-off"></span>Can’t pick (past, closed or too far ahead)</li>
    <li><span class="rb-availability__sw rb-availability__sw-today" aria-hidden="true"></span>Today</li>
    <li><span class="rb-availability__sw rb-availability__sw-selected" aria-hidden="true"></span>Selected day</li>
  </ul>
</section>

