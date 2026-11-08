{{-- One room's or block's week as a grid, on the timetable pages. Needs $grid (ManageTimetableController::week()), $editable, $page.
     Each class sits at its time; things at the same time sit side by side with a red outline. --}}
@use('App\Support\TimetableRules')
@use('App\Support\TimeSlots')
@php $hour = 46; /* pixels per hour */ @endphp
<h3 class="tt-listhead">{{ $grid['kind'] === 'room' ? $grid['name'] : 'Block '.$grid['name'] }}: the week <span class="count">{{ $grid['count'] }}</span></h3>
@if (! $grid['count'])
  <p class="empty">Nothing on the timetable for {{ $grid['name'] }}.</p>
@else
  <div class="tt-week-scroll" role="region" tabindex="0" aria-label="{{ $grid['kind'] === 'room' ? $grid['name'] : 'Block '.$grid['name'] }}: the week, by day and hour; scroll horizontally on small screens">
    <div class="tt-week" style="--days: {{ count($grid['days']) }}; --tt-height: {{ count($grid['hours']) * $hour }}px; --tt-hour: {{ $hour }}px">
      <div class="tt-corner"></div>
      @foreach ($grid['days'] as $d => $items)
        <div class="tt-dayname"><a href="{{ route($page, ['day' => $d]) }}">{{ $d }}</a></div>
      @endforeach
      <div class="tt-hours" aria-hidden="true">
        @foreach ($grid['hours'] as $i => $minutes)
          <span style="top: {{ $i * $hour }}px">{{ TimeSlots::clock($minutes) }}</span>
        @endforeach
      </div>
      @foreach ($grid['days'] as $d => $items)
        <div class="tt-daycol">
          @foreach ($items as $item)
            @php $row = $item['row']; @endphp
            <a @class(['tt-item', 'blocked' => $row['type'] === 'Blocked', 'overlap' => $item['overlap']])
               href="{{ $editable ? route('manage.timetable.rows.edit', $row['id']) : route($page, ['day' => $d]) }}"
               style="top: {{ $item['top'] }}%; height: {{ $item['height'] }}%; left: calc({{ $item['left'] }}% + 2px); width: calc({{ $item['width'] }}% - 4px)"
               title="{{ $d }} {{ TimetableRules::span($row) }} · {{ $row['type'] === 'Blocked' ? 'Not available'.($row['note'] ? ': '.$row['note'] : '') : trim($row['code'].' '.$row['title']) }}{{ $row['block'] ? ' · '.$row['block'] : '' }} · {{ $row['room'] }}{{ $row['instructor'] ? ' · '.$row['instructor'] : '' }}">
              <span class="tt-item-time">{{ TimetableRules::span($row) }}</span>
              <strong>{{ $row['type'] === 'Blocked' ? 'Not available' : $row['code'] }}</strong>
              <span>{{ $row['type'] === 'Blocked' ? $row['note'] : ($grid['kind'] === 'room' ? $row['block'] : $row['room']) }}</span>
            </a>
          @endforeach
        </div>
      @endforeach
    </div>
  </div>
  <p class="hint">{{ $editable ? 'Click a class to change it.' : 'Click a class to see its day.' }} Hover over one for all its details. A red outline means two things at the same time.</p>
@endif
