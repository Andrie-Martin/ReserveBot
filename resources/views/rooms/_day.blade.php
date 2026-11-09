@use('App\Support\TimeSlots')
<section class="rb-card rb-availability__day-card" id="day" aria-labelledby="day-title">
  <div class="rb-availability__day-head">
    <div>
      <p class="rb-availability__eyebrow">Rooms on</p>
      <h2 id="day-title">{{ $date->format('l, F j') }}@if ($date->isToday()) <span class="rb-availability__todaytag">Today</span>@endif</h2>
    </div>
    <div class="rb-availability__daynav">
      <x-ui.link-button href="#calendar-and-agenda">Calendar and agenda</x-ui.link-button>
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


  @endif
</section>
