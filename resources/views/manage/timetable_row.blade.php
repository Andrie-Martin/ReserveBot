@extends('layouts.app')
@use('App\Support\TimetableRules')

@section('title', 'Change a row')

@section('content')
<section class="card manage-card">
  <h1>Manage</h1>
  @include('manage._tabs')
  <p class="back"><a href="{{ $back }}">← New timetable</a></p>

  <h2 class="edit-title">{{ $row['type'] === 'Blocked' ? 'Time the room isn’t available' : ($row['code'] ?? 'A class') }}@if ($row['block']) · {{ $row['block'] }}@endif</h2>
  <p class="muted small">{{ $row['day'] ?? '?' }} {{ TimetableRules::span($row) }} · {{ $row['room'] ?? 'no room' }}@if ($row['line']) · row {{ $row['line'] }} of the file @endif · in the new timetable (not in use yet)</p>

  @if ($problems)
    <div class="error" role="alert">
      <p><strong>To fix:</strong></p>
      <ul>
        @foreach ($problems as $text)
          <li>{{ $text }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('manage.timetable.rows.update', $row['id']) }}" class="mform tt-form tt-edit">
    @csrf
    @method('PUT')
    @include('manage._timetable_row_form', ['prefix' => 'edit', 'row' => $row, 'bag' => $errors->getBag('row')])
    <button type="submit" class="btn-small">Save</button>
  </form>

  <form method="post" action="{{ route('manage.timetable.rows.destroy', $row['id']) }}" class="tt-delete-form">
    @csrf
    @method('DELETE')
    <input type="hidden" name="back" value="{{ $back }}">
    <button type="submit" class="btn-small btn-danger">Delete this row</button>
  </form>

  @include('manage._timetable_datalists')
</section>
@endsection
