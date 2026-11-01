@extends('layouts.app')

@section('title', $title)

@section('content')
<section class="card">
  <h1>Welcome, {{ $user->name }}</h1>
  <p class="muted">
    {{ $user->department->name }}@if ($user->section) · {{ $user->section->name }}@endif
  </p>
  <p>{{ $intro }}</p>

  @if ($stat)
    <p class="stat"><strong>{{ $stat['value'] }}</strong> {{ $stat['label'] }}</p>
  @endif

  @if ($classes !== null)
    <h2>Your block's classes this semester</h2>
    @if ($classes->isEmpty())
      <p class="muted">No classes found for your block this semester.</p>
    @else
      <table>
        <thead>
          <tr><th>Day</th><th>Time</th><th>Room</th><th>Course</th><th>Instructor</th></tr>
        </thead>
        <tbody>
          @foreach ($classes as $class)
            <tr>
              <td>{{ $class->day_of_week }}</td>
              <td>{{ $class->time_range }}</td>
              <td>{{ $class->room->name }}</td>
              <td>{{ $class->course->code }}@if ($class->course->title) · {{ $class->course->title }}@endif</td>
              <td>{{ $class->instructor }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  @endif

  <h2>Coming in the next phases</h2>
  <ul>
    @foreach ($comingNext as $item)
      <li>{{ $item }}</li>
    @endforeach
  </ul>
</section>
@endsection
