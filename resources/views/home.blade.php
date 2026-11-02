@extends('layouts.app')
@use('App\Services\ClassChecks')
@use('App\Support\TimeSlots')

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

  @if (! empty($meetings))
    {{-- Phase 10: is each of the block's classes face-to-face? Online frees the room for others. --}}
    <h2 id="face-to-face">Face-to-face this week?</h2>
    @if (session('status'))
      <p class="success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('problem'))
      <p class="error" role="alert">{{ session('problem') }}</p>
    @endif
    <p class="hint meet-hint">We email you a day, 5 hours and 1 hour before each class. <strong>Online</strong> frees the room for others, and so does no answer by the 5-hour email.</p>
    <ul class="mlist meet-list">
      @foreach ($meetings as $m)
        @php $freed = in_array($m['state'], ['online', 'freed'], true); @endphp
        <li @class(['freed' => $freed])>
          <span class="mlist-when">{{ $m['start']->isToday() ? 'Today' : ($m['start']->isTomorrow() ? 'Tomorrow' : $m['start']->format('D, M j')) }} · {{ TimeSlots::span($m['start']->hour * 60 + $m['start']->minute, $m['end']->hour * 60 + $m['end']->minute) }}</span>
          <span class="mlist-what">{{ $m['class']->course?->code ?? 'Class' }} · {{ $m['class']->room->name }}</span>
          <span class="st {{ ClassChecks::BADGES[$m['state']] }}">{{ ClassChecks::LABELS[$m['state']] }}</span>
          @include('classes._answer', ['m' => $m, 'route' => 'classes.answer'])
        </li>
      @endforeach
    </ul>
  @endif

  @if ($classes !== null)
    <h2>Your block's classes this semester</h2>
    @if ($classes->isEmpty())
      <p class="muted">No classes found for your block this semester.</p>
    @else
      <div class="table-scroll">
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
      </div>
    @endif
  @endif

  @if (! empty($ready))
    <h2>Ready to use</h2>
    <p class="ready">
      @foreach ($ready as $link)
        <a class="btn-ghost" href="{{ $link['url'] }}">{{ $link['label'] }} →</a>
      @endforeach
    </p>
  @endif

  @if (! empty($comingNext))
    <h2>Coming in the next phases</h2>
    <ul>
      @foreach ($comingNext as $item)
        <li>{{ $item }}</li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
