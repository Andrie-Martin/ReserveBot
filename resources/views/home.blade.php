@extends('layouts.app')
@use('App\Services\ClassChecks')
@use('App\Support\TimeSlots')

@section('title', $title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
  @endpush

@section('content')
@if ($user->role === 'rep')
  @include('home._representative')
@else
<section class="card rb-dashboard rb-dashboard--staff rb-ui">
  <header class="rb-dashboard__intro">
    <p class="rb-dashboard__kicker">{{ $user->roleLabel() }}</p>
    <h1>Welcome, {{ $user->name }}</h1>
    <p class="muted">
      {{ $user->department->name }}@if ($user->section) · {{ $user->section->name }}@endif
    </p>
  </header>
  @include('home._login-notice')
  <div class="rb-dashboard__banner">
    <p class="rb-dashboard__banner-kicker">Your workspace</p>
    <p class="rb-dashboard__banner-text">{{ $intro }}</p>
  </div>

  @if (! empty($stats))
    {{-- Three read-only counts for the viewer's own department; each says what it counts. --}}
    <section class="rb-dashboard__stats" aria-label="At a glance">
      @foreach ($stats as $stat)
        <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :note="$stat['note']" />
      @endforeach
    </section>
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
    <x-ui.card class="rb-dashboard__shortcuts">
    <h2>Ready to use</h2>
    <div class="rb-dashboard__links">
      @foreach ($ready as $link)
        @php
          $icon = match (true) {
            $link['url'] === route('today') => 'today',
            $link['url'] === route('requests') => 'requests',
            $link['url'] === route('manage.rooms'), $link['url'] === route('manage.timetable') => 'manage',
            $link['url'] === route('accounts.index') => 'accounts',
            default => 'rooms',
          };
        @endphp
        <x-ui.link-button variant="secondary" :href="$link['url']" class="rb-dashboard__action">
          <span class="rb-dashboard__action-icon"><x-ui.icon :name="$icon" /></span>
          <span class="rb-dashboard__action-label">{{ $link['label'] }}</span> <span class="rb-dashboard__action-arrow" aria-hidden="true">→</span>
        </x-ui.link-button>
      @endforeach
    </div>
    </x-ui.card>
  @endif

</section>
@endif
@endsection
