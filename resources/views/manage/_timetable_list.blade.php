{{-- One day's rows (or the rows to fix), on the timetable pages. Needs $list, $day, $problems, $warned, $editable, $page, $here --}}
@use('App\Support\TimetableRules')
@php $fix = $day === 'fix'; @endphp
<h3 class="tt-listhead">{{ $fix ? 'Rows to fix' : TimetableRules::DAY_NAMES[$day] }} <span class="count">{{ count($list) }}</span></h3>
@if ($editable && ! $fix)
  {{-- Above the list, so it's easy to find on a long day --}}
  <details class="add-box tt-add" @if ($errors->getBag('row')->any()) open @endif>
    <summary>+ Add to {{ TimetableRules::DAY_NAMES[$day] }}</summary>
    <form method="post" action="{{ route('manage.timetable.rows.store') }}" class="mform tt-form">
      @csrf
      @include('manage._timetable_row_form', ['prefix' => 'add', 'row' => null, 'defaultDay' => $day, 'bag' => $errors->getBag('row')])
      <button type="submit" class="btn-small">Add</button>
    </form>
  </details>
@endif
@if (! $list)
  <p class="empty">{{ $fix ? 'Nothing to fix.' : 'Nothing on '.TimetableRules::DAY_NAMES[$day].'.' }}</p>
@else
  <table class="mtable tt-table">
    <thead>
      <tr>
        @if ($fix)<th>Day</th>@endif
        <th>Room</th>
        <th>Time</th>
        <th>Class</th>
        <th class="hide-sm">Instructor</th>
        @if ($editable)<th><span class="sr-only">Change</span></th>@endif
      </tr>
    </thead>
    <tbody>
      @foreach ($list as $row)
        @php
          $rowProblems = $problems[$row['id']] ?? [];
          $rowWarnings = $warned[$row['id']] ?? [];
        @endphp
        <tr @class(['tt-bad-row' => $rowProblems, 'tt-warn-row' => ! $rowProblems && $rowWarnings, 'tt-blocked-row' => $row['type'] === 'Blocked'])>
          @if ($fix)<td class="tt-day">{{ $row['day'] ?? '—' }}</td>@endif
          <td class="tt-room">{{ $row['room'] ?? '—' }}</td>
          <td class="tt-time">{{ TimetableRules::span($row) }}</td>
          <td class="tt-what">
            @if ($row['type'] === 'Blocked')
              <strong>Not available</strong>@if ($row['note'])<span class="extra">{{ $row['note'] }}</span>@endif
            @else
              <strong>{{ $row['code'] ?? 'No course code' }}</strong>@if ($row['block']) · {{ $row['block'] }}@endif
              @if ($row['title'])<span class="extra">{{ $row['title'] }}</span>@endif
              @if ($row['note'])<span class="extra">Note: {{ $row['note'] }}</span>@endif
            @endif
            @if ($row['instructor'])<span class="extra show-sm">{{ $row['instructor'] }}</span>@endif
            @if ($editable && $row['line'] && ($rowProblems || $rowWarnings))<span class="extra">Row {{ $row['line'] }} of the file</span>@endif
            @foreach ($rowProblems as $text)
              <span class="tt-msg tt-msg-bad">{{ $text }}</span>
            @endforeach
            @foreach ($rowWarnings as $text)
              <span class="tt-msg tt-msg-warn">⚠ {{ $text }}</span>
            @endforeach
          </td>
          <td class="hide-sm">{{ $row['instructor'] }}</td>
          @if ($editable)
            <td class="tt-row-actions">
              <a href="{{ route('manage.timetable.rows.edit', $fix ? ['row' => $row['id'], 'back' => 'fix'] : ['row' => $row['id']]) }}">Edit<span class="sr-only"> {{ TimetableRules::describe($row) }}</span></a>
              <form method="post" action="{{ route('manage.timetable.rows.destroy', $row['id']) }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="back" value="{{ $here }}">
                <button type="submit" class="link tt-delete">Delete<span class="sr-only"> {{ TimetableRules::describe($row) }}</span></button>
              </form>
            </td>
          @endif
        </tr>
      @endforeach
    </tbody>
  </table>
@endif
