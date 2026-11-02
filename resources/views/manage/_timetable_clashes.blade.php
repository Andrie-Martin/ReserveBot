{{-- Upcoming bookings at the same time as a class (or blocked time) in the same room.
     $clashes: [['booking' => Reservation, 'row' => timetable row], ...] --}}
@use('App\Support\TimetableRules')
@use('App\Support\TimeSlots')
<ul class="mlist tt-clashes">
  @foreach ($clashes as $clash)
    @php
      $b = $clash['booking'];
      $row = $clash['row'];
      $from = $b->start_time->hour * 60 + $b->start_time->minute;
      $to = $b->end_time->isSameDay($b->start_time) ? $b->end_time->hour * 60 + $b->end_time->minute : 1440;
    @endphp
    <li>
      <span class="mlist-when">{{ $b->start_time->format('D, M j') }} · {{ TimeSlots::span($from, $to) }}</span>
      <span class="mlist-what">
        {{ $b->room?->name }} · “{{ $b->purpose }}” <span class="muted">({{ $b->user->name }}@if ($b->user->section), {{ $b->user->section->name }}@endif)</span>
        <span class="extra">
          @if ($row['type'] === 'Blocked')
            The timetable says the room isn’t available then ({{ TimetableRules::span($row) }}{{ $row['note'] ? ', '.$row['note'] : '' }}).
          @else
            The timetable has {{ TimetableRules::what($row) }} there, {{ TimetableRules::span($row) }}.
          @endif
        </span>
      </span>
      <span class="status-badge st-{{ strtolower($b->status) }}">{{ $b->statusLabel() }}</span>
      <a href="{{ route('bookings.show', $b) }}">Open<span class="sr-only"> booking {{ $b->id }}</span></a>
    </li>
  @endforeach
</ul>
