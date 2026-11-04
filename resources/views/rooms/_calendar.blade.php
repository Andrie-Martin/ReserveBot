@use('App\Support\TimeSlots')
<section class="rb-card rb-availability__cal-card" aria-labelledby="cal-title">
  <div class="rb-availability__cal-intro">
    <h1>Free rooms</h1>
    <p class="rb-availability__muted">{{ $department->name }} · Pick a day, up to {{ $lastDay->format('F j') }}, to see which rooms are free.</p>
  </div>

  <div class="rb-availability__cal-bar">
    <h2 id="cal-title" class="rb-availability__cal-title">{{ $calendar['title'] }}</h2>
    <div class="rb-availability__cal-nav">
      <x-ui.link-button variant="secondary" class="rb-availability__btn-ghost" :href="$todayUrl">Today</x-ui.link-button>
      @if ($calendar['prevUrl'])
        <x-ui.link-button variant="secondary" class="rb-availability__cal-arrow" :href="$calendar['prevUrl']" aria-label="Previous month">‹</x-ui.link-button>
      @else
        <span class="rb-availability__cal-arrow rb-availability__off" aria-hidden="true">‹</span>
      @endif
      @if ($calendar['nextUrl'])
        <x-ui.link-button variant="secondary" class="rb-availability__cal-arrow" :href="$calendar['nextUrl']" aria-label="Next month">›</x-ui.link-button>
      @else
        <span class="rb-availability__cal-arrow rb-availability__off" aria-hidden="true">›</span>
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
  </ul>
</section>

