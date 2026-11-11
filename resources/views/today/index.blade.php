@extends('layouts.app')
@use('App\Services\ClassChecks')
@use('App\Support\TimeSlots')

@section('title', "Today's schedule")

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/operations.css') }}?v={{ filemtime(public_path('css/operations.css')) }}">
@endpush

@section('content')
<section class="card today-card rb-booking rb-ops rb-ui">
  <div class="today-head">
    <div>
      <p class="eyebrow">{{ $date->isToday() ? 'Today' : ($date->isPast() ? 'Earlier day' : 'Coming day') }}</p>
      <h1>{{ $date->format('l, F j') }}</h1>
      <p class="muted">{{ $department->name }} · Hand out keys and items, take them back, and mark no-shows.</p>
    </div>
    <div class="daynav">
      @if ($prevUrl)
        <a class="btn-ghost" href="{{ $prevUrl }}">‹ Previous day</a>
      @endif
      @unless ($date->isToday())
        <a class="btn-ghost" href="{{ route('today') }}">Today</a>
      @endunless
      @if ($nextUrl)
        <a class="btn-ghost" href="{{ $nextUrl }}">Next day ›</a>
      @endif
    </div>
  </div>

  {{-- After marking a class the result is shown in the class list (below), where the page returns to. --}}
  @if (session('status') && ! session('class_marked'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem') && ! session('class_marked'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  <ul class="today-stats" aria-label="Summary">
    <li><strong>{{ $inUse->count() }}</strong> in use now</li>
    <li><strong>{{ $toHandOut->count() }}</strong> to hand out</li>
    <li><strong>{{ $waiting->count() }}</strong> waiting for approval</li>
    <li><strong>{{ $done->count() }}</strong> done</li>
  </ul>

  <div class="rb-booking__panel">
  <h2>In use now <span class="count">{{ $inUse->count() }}</span></h2>
  @forelse ($inUse as $r)
    @include('today._card', ['r' => $r, 'staff' => $staff])
  @empty
    <p class="empty">Nothing is out right now.</p>
  @endforelse
  </div>

  <div class="rb-booking__panel">
  <h2>To hand out <span class="count">{{ $toHandOut->count() }}</span></h2>
  @forelse ($toHandOut as $r)
    @include('today._card', ['r' => $r, 'staff' => $staff])
  @empty
    <p class="empty">No approved bookings left to hand out{{ $date->isToday() ? ' today' : '' }}.</p>
  @endforelse
  </div>

  @if ($waiting->isNotEmpty())
    <div class="rb-booking__panel">
    <h2>Waiting for approval <span class="count">{{ $waiting->count() }}</span></h2>
    @foreach ($waiting as $r)
      @include('today._card', ['r' => $r, 'staff' => $staff])
    @endforeach
    </div>
  @endif

  @if ($done->isNotEmpty())
    <div class="rb-booking__panel">
    <details class="done-list">
      <summary>Done <span class="count">{{ $done->count() }}</span> <span class="muted">completed, no-shows, cancelled and rejected</span></summary>
      @foreach ($done as $r)
        @include('today._card', ['r' => $r, 'staff' => $staff])
      @endforeach
    </details>
    </div>
  @endif

  @if ($classes->isNotEmpty())
    {{-- Phase 10: the department's classes this day. Online, or not confirmed by the 5-hour email, frees the room. --}}
    @php
      $freedCount = collect($classes)->filter(fn ($m) => in_array($m['state'], ['online', 'freed'], true))->count();
      $waitingCount = collect($classes)->where('state', 'waiting')->count();
    @endphp
    <div class="rb-booking__panel class-list" id="classes">
      <h2 class="class-list__head">Classes <span class="count">{{ $classes->count() }}</span>
        <span class="class-list__badge">{{ $freedCount }} {{ $freedCount === 1 ? 'Room' : 'Rooms' }} Freed</span>
        <span class="class-list__badge">{{ $waitingCount }} Not Confirmed Yet</span></h2>
      @if (session('class_marked') && session('status'))
        <p class="success" role="status">{{ session('status') }}</p>
      @elseif (session('class_marked') && session('problem'))
        <p class="error" role="alert">{{ session('problem') }}</p>
      @endif
      <table class="mtable class-table">
        <thead>
          <tr><th>Time</th><th>Room</th><th>Class</th><th>Face-to-face?</th><th><span class="sr-only">Mark</span></th></tr>
        </thead>
        <tbody>
          @foreach ($classPager as $m)
            @php $freed = in_array($m['state'], ['online', 'freed'], true); @endphp
            <tr @class(['off' => $freed])>
              <td class="nowrap">{{ TimeSlots::span($m['start']->hour * 60 + $m['start']->minute, $m['end']->hour * 60 + $m['end']->minute) }}</td>
              <td>{{ $m['class']->room->name }}</td>
              <td>{{ $m['class']->course?->code ?? 'Class' }}@if ($m['class']->section) <span class="muted">· {{ $m['class']->section->name }}</span>@endif</td>
              <td>
                <span class="st {{ ClassChecks::BADGES[$m['state']] }}">{{ ClassChecks::LABELS[$m['state']] }}</span>
                @if ($m['check']?->answeredBy)
                  <span class="muted small">by {{ $m['check']->answeredBy->name }}</span>
                @endif
              </td>
              <td>@include('classes._answer', ['m' => $m, 'route' => 'classes.mark'])</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <x-ui.pager :paginator="$classPager" noun="classes" />
      <p class="hint">Block accounts get asked by email a day before. <strong>Online</strong>, or no answer by the 5-hour email, frees the room: it shows as free on Free rooms and can be booked. Mark a class here if the instructor tells you.</p>
    </div>
  @endif

  <p class="hint">The buttons work at any time, on any day. A booking shows <strong>Late</strong> when it isn’t picked up {{ $staff->graceMinutes }} minutes after it starts, and <strong>Overdue</strong> when items are still out after it ends.@if ($date->gte(today())) For each room’s full day, see <a href="{{ route('rooms', ['date' => $date->toDateString()]) }}#day">Free rooms</a>.@endif</p>
</section>
@endsection
