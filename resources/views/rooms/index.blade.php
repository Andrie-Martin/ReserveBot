@extends('layouts.app')
@use('App\Support\TimeSlots')
@use('App\Services\RoomAvailability')

@section('title', 'Free rooms')
@section('main-class', 'wide')

@section('content')
@php
  $clock = '<svg class="ico" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.25"/><path d="M8 4.75V8l2.25 1.5"/></svg>';
  $pin = '<svg class="ico" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 14.5s4.75-4.2 4.75-8a4.75 4.75 0 0 0-9.5 0c0 3.8 4.75 8 4.75 8z"/><circle cx="8" cy="6.5" r="1.6"/></svg>';
  $box = '<svg class="ico" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 5 8 2.25 13.5 5v6L8 13.75 2.5 11z"/><path d="M2.5 5 8 7.75 13.5 5M8 7.75v6"/></svg>';
  // Words for a status tag in a room's list: a class whose room is booked, or a booking
  $statusWords = fn ($item) => ($item['kind'] === 'class' ? 'room booked, ' : '').RoomAvailability::STATUS_WORDS[$item['status']];
@endphp

{{-- ============ The month calendar ============ --}}
<section class="card cal-card" aria-labelledby="cal-title">
  <div class="cal-intro">
    <h1>Free rooms</h1>
    <p class="muted">{{ $department->name }} · Pick a day, up to {{ $lastDay->format('F j') }}, to see which rooms are free.</p>
  </div>

  <div class="cal-bar">
    <h2 id="cal-title" class="cal-title">{{ $calendar['title'] }}</h2>
    <div class="cal-nav">
      <a class="btn-ghost" href="{{ $todayUrl }}">Today</a>
      @if ($calendar['prevUrl'])
        <a class="cal-arrow" href="{{ $calendar['prevUrl'] }}" aria-label="Previous month">‹</a>
      @else
        <span class="cal-arrow off" aria-hidden="true">‹</span>
      @endif
      @if ($calendar['nextUrl'])
        <a class="cal-arrow" href="{{ $calendar['nextUrl'] }}" aria-label="Next month">›</a>
      @else
        <span class="cal-arrow off" aria-hidden="true">›</span>
      @endif
    </div>
  </div>

  <div class="cal-wrap">
    <table class="calendar">
      <thead>
        <tr>
          @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $weekday)
            <th scope="col"><span class="long">{{ $weekday }}</span><span class="short" aria-hidden="true">{{ substr($weekday, 0, 3) }}</span></th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach ($calendar['weeks'] as $week)
          <tr>
            @foreach ($week as $day)
              @php
                $items = $day['items'];
                $shown = count($items) > 3 ? 2 : 3;   // keep every day to three lines
              @endphp
              <td @class(['day', 'st-'.$day['state'], 'out' => ! $day['inMonth'], 'today' => $day['isToday'], 'selected' => $day['isSelected'], 'pick' => $day['url']])>
                @if ($day['url'])
                  <a class="cell" href="{{ $day['url'] }}" @if ($day['isSelected']) aria-current="date" @endif>
                @else
                  <div class="cell">
                @endif
                    <span class="daynum" aria-hidden="true">@if ($day['date']->day === 1 && ! $day['inMonth'])<span class="mon">{{ $day['date']->format('M') }} </span>@endif{{ $day['date']->day }}</span>
                    <span class="sr-only">{{ $day['date']->format('l, F j') }}{{ $day['isToday'] ? ', today' : '' }}{{ $day['state'] === 'closed' ? ', closed' : '' }}</span>
                    @if ($day['state'] === 'closed')
                      <span class="daynote">Closed</span>
                    @endif
                    @if ($items)
                      <span class="evts">
                        @foreach (array_slice($items, 0, $shown) as $it)
                          @php $booked = $it['kind'] === 'class' && in_array($it['status'], ['Confirmed', 'InUse'], true); @endphp
                          <span @class(['evt', 'k-'.$it['kind'], 'pending' => $it['status'] === 'Pending', 'booked' => $booked, 'freed' => ! empty($it['freed'])]) title="{{ $it['tip'] }}{{ ! empty($it['check']) ? ' · '.$it['check'] : '' }}">
                            <span class="t">{!! $clock !!}<b>{{ TimeSlots::clock($it['start']) }}</b> <span class="x">{{ $it['text'] }}</span></span>
                            <span class="w">{!! $it['noRoom'] ? $box : $pin !!}{{ $it['room'] }}</span>
                            @if ($booked)<span class="ok" aria-hidden="true">✓</span>@endif
                            @if ($it['tag'])<span class="sr-only">({{ $it['tag'] }})</span>@endif
                            @if (! empty($it['check']))<span class="sr-only">({{ $it['check'] }})</span>@endif
                          </span>
                        @endforeach
                        @if (count($items) > $shown)
                          <span class="more">+{{ count($items) - $shown }} more</span>
                        @endif
                      </span>
                      <span class="dots" aria-hidden="true">
                        @foreach (array_slice($items, 0, 4) as $it)<i @class(['k-'.$it['kind'], 'pending' => $it['status'] === 'Pending'])></i>@endforeach
                      </span>
                    @endif
                @if ($day['url'])
                  </a>
                @else
                  </div>
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <ul class="legend" aria-label="What the colours mean">
    @if ($staffView)
      <li><span class="sw sw-booking"></span>Booking by your department</li>
    @else
      <li><span class="sw sw-class"></span>Your block’s class</li>
      <li><span class="sw sw-booking"></span>Your booking</li>
      <li><span class="ok">✓</span>Booked for the class</li>
    @endif
    <li><span class="sw sw-pending"></span>Waiting for approval</li>
    <li><span class="sw sw-off"></span>Can’t pick (past, closed or too far ahead)</li>
  </ul>
</section>

{{-- ============ The chosen day ============ --}}
<section class="card day-card" id="day" aria-labelledby="day-title">
  <div class="day-head">
    <div>
      <p class="eyebrow">Rooms on</p>
      <h2 id="day-title">{{ $date->format('l, F j') }}@if ($date->isToday()) <span class="todaytag">Today</span>@endif</h2>
    </div>
    <div class="daynav">
      @if ($prevUrl)
        <a class="btn-ghost" href="{{ $prevUrl }}">‹ Previous day</a>
      @else
        <span class="btn-ghost off" aria-disabled="true">‹ Previous day</span>
      @endif
      @if ($nextUrl)
        <a class="btn-ghost" href="{{ $nextUrl }}">Next day ›</a>
      @else
        <span class="btn-ghost off" aria-disabled="true">Next day ›</span>
      @endif
    </div>
  </div>

  @if ($dateError)
    <p class="error">{{ $dateError }}</p>
  @endif

  @if (! $isOpen)
    <p class="notice">Rooms aren’t booked on {{ $date->format('l') }}s. Pick another day in the calendar.</p>
  @else
    @unless ($inTerm)
      <p class="notice">This day is outside the semester, so there are no classes. Only bookings are shown.</p>
    @endunless

    {{-- What the viewer has on this day (the calendar only fits three) --}}
    <div class="dayplan">
      <h3>{{ $staffView ? 'Bookings this day' : 'Your day' }}</h3>
      @if ($dayItems)
        <ul class="agenda">
          @foreach ($dayItems as $it)
            <li @class(['k-'.$it['kind'], 'pending' => $it['status'] === 'Pending'])>
              <span class="when">{{ TimeSlots::span($it['start'], $it['end']) }}</span>
              <span class="what">{{ $it['title'] }} <span class="where">· {{ $it['room'] }}</span></span>
              @if ($it['tag'])
                <span @class(['st', 'st-pending' => $it['status'] === 'Pending'])>{{ $it['tag'] }}</span>
              @endif
              @if (! empty($it['check']))
                {{-- Phase 10: is the class face-to-face? --}}
                <span @class(['st', 'st-freed' => ! empty($it['freed'])])>{{ $it['check'] }}</span>
              @endif
              @if (! empty($it['bookUrl']))
                <a class="btn-book" href="{{ $it['bookUrl'] }}">Book<span class="sr-only"> {{ $it['text'] }} at {{ TimeSlots::clock($it['start']) }}</span></a>
              @elseif ($it['url'])
                <a class="agenda-link" href="{{ $it['url'] }}">Details<span class="sr-only"> of {{ $it['title'] }}</span></a>
              @endif
            </li>
          @endforeach
        </ul>
      @else
        <p class="empty">{{ $staffView ? 'No bookings from your department.' : 'No classes or bookings for your block.' }}</p>
      @endif
    </div>

    <form class="finder" method="get" action="{{ route('rooms') }}#day">
      <input type="hidden" name="date" value="{{ $date->toDateString() }}">
      <span class="finder-label">Find a room free from</span>
      <label for="from" class="sr-only">Start time</label>
      <select id="from" name="from" required>
        <option value="">Start</option>
        @foreach ($slots as $t)
          <option value="{{ TimeSlots::hhmm($t) }}" @selected($search && $search['from'] === $t)>{{ TimeSlots::clock($t) }}</option>
        @endforeach
      </select>
      <span>to</span>
      <label for="to" class="sr-only">End time</label>
      <select id="to" name="to" required>
        <option value="">End</option>
        @foreach ($slots as $t)
          <option value="{{ TimeSlots::hhmm($t + $step) }}" @selected($search && $search['to'] === $t + $step)>{{ TimeSlots::clock($t + $step) }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn-small">Find rooms</button>
      @if ($search || $searchError)
        <a class="quiet" href="{{ route('rooms', ['date' => $date->toDateString()]) }}#day">Clear</a>
      @endif
    </form>

    @if ($searchError)
      <p class="error">{{ $searchError }}</p>
    @endif

    @if ($search)
      @php $found = count($search['free']); @endphp
      <div @class(['results', 'none-free' => $found === 0]) role="status">
        @if ($found > 0)
          <p><strong>{{ $found }} {{ $found === 1 ? 'room is' : 'rooms are' }} free</strong> from {{ TimeSlots::range($search['from'], $search['to']) }}:
            {{ collect($search['free'])->pluck('name')->join(', ', ' and ') }}.</p>
        @else
          <p><strong>No room is free</strong> from {{ TimeSlots::range($search['from'], $search['to']) }}. Try a shorter time or another day.</p>
        @endif
        @if ($search['started'])
          <p class="small">Part of that time has already passed today.</p>
        @endif
      </div>
    @endif

    <h3 class="list-title">Free times in each room</h3>
    @if ($now !== null && $slots && $now > $slots[0])
      <p class="muted small">Times that have already started today aren’t shown as free.</p>
    @endif

    <ul class="roomlist">
      @foreach ($roomList as $row)
        @php $room = $row['room']; @endphp
        <li @class(['room-row', 'is-match' => $row['match'] === true, 'is-clash' => $row['match'] === false])>
          <div class="room-name">
            <strong>{{ $room->name }}</strong>
            <span>{{ $room->capacity ? $room->capacity.' seats' : 'Seats not recorded' }}</span>
            @if ($row['bookUrl'])
              <a class="btn-book" href="{{ $row['bookUrl'] }}">Book<span class="sr-only"> {{ $room->name }}</span></a>
            @endif
          </div>
          <div class="room-body">
            @if ($row['match'] === true)
              <p class="matchline">✓ Free {{ TimeSlots::span($search['from'], $search['to']) }}</p>
            @elseif ($row['clash'] && ! $row['allDay'])
              <p class="clashline">Busy then: {{ $row['clash']['label'] }} ({{ TimeSlots::span($row['clash']['start'], $row['clash']['end']) }})</p>
            @endif

            <div class="room-free">
              @if ($row['allDay'])
                <span class="none">Blocked all day: {{ $row['allDay'] }}</span>
              @elseif (! $row['free'])
                <span class="none">No free time {{ $date->isToday() ? 'left today' : 'this day' }}</span>
              @else
                <span class="sr-only">Free</span>
                @foreach ($row['free'] as [$a, $b])
                  <span class="free-chip">{{ TimeSlots::span($a, $b) }}</span>
                @endforeach
              @endif
            </div>

            @if ($row['busy'] && ! $row['allDay'])
              <details class="room-busy">
                <summary>What’s on ({{ count($row['busy']) }})</summary>
                <ul>
                  @foreach ($row['busy'] as $it)
                    <li @class(['mine' => $it['mine']])>
                      <span class="when">{{ TimeSlots::span($it['start'], $it['end']) }}</span>
                      <span class="what">
                        {{ $it['label'] }}
                        @if ($it['status'])
                          <span @class(['st', 'st-pending' => $it['status'] === 'Pending'])>{{ $statusWords($it) }}</span>
                        @endif
                        @foreach ($it['detail'] as $line)
                          @if ($line !== $it['label'])<span class="extra">{{ $line }}</span>@endif
                        @endforeach
                      </span>
                    </li>
                  @endforeach
                </ul>
              </details>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
