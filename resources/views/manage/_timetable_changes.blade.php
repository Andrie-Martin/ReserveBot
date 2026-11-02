{{-- Rows of a timetable as a compact table (the check page's New and Removed lists). Needs $rows. --}}
@use('App\Support\TimetableRules')
<div class="table-scroll">
  <table class="mtable tt-small">
    <thead>
      <tr><th>Day</th><th>Time</th><th>Room</th><th>Class</th><th>Instructor</th></tr>
    </thead>
    <tbody>
      @foreach ($rows as $row)
        <tr @class(['tt-blocked-row' => $row['type'] === 'Blocked'])>
          <td>{{ $row['day'] }}</td>
          <td class="tt-time">{{ TimetableRules::span($row) }}</td>
          <td>{{ $row['room'] }}</td>
          <td>{{ $row['type'] === 'Blocked' ? 'Not available'.($row['note'] ? ': '.$row['note'] : '') : TimetableRules::what($row).($row['title'] ? ' · '.$row['title'] : '') }}</td>
          <td>{{ $row['instructor'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
