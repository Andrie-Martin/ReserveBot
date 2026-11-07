@extends('layouts.app')
@use('App\Support\TimetableRules')

@section('title', $draft ? 'Edit new timetable' : 'View timetable in use')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/timetable.css') }}?v={{ filemtime(public_path('css/timetable.css')) }}">
  <script src="{{ asset('js/timetable.js') }}?v={{ filemtime(public_path('js/timetable.js')) }}" defer></script>
@endpush

@section('content')
@php
  $editable = (bool) $draft;
  $page = $editable ? 'manage.timetable.draft' : 'manage.timetable.view';
  $here = request()->fullUrl();
  $problemCount = count($problems);
@endphp
<section class="card manage-card tt-card rb-booking rb-manage rb-tt rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>{{ $draft ? 'Edit new timetable' : 'View timetable in use' }}</h1>
  @include('manage._tabs')
  </header>

  <div class="rb-booking__panel">
  @if ($undo)
    {{-- Right after a delete: put the row back --}}
    <form method="post" action="{{ route('manage.timetable.rows.undo') }}" class="tt-undo">
      @csrf
      <input type="hidden" name="row" value="{{ $undo }}">
      <input type="hidden" name="back" value="{{ $here }}">
      <button type="submit" class="btn-small btn-outline">Undo the delete</button>
    </form>
  @endif
  <p class="back"><a href="{{ route('manage.timetable') }}">← Timetable</a></p>

  <div class="tt-head">
    <div>
      @if ($editable)
        <h2 class="edit-title">New timetable <span class="tt-tag">not in use yet</span></h2>
        <p class="muted small">{{ $draft->sourceLabel() }} · {{ count($rows) }} {{ Str::plural('row', count($rows)) }}@if ($draft->term_name) · for {{ $draft->term_name }}@endif. Nobody sees changes made here until it replaces the timetable.</p>
      @else
        <h2 class="edit-title">Timetable in use</h2>
        <p class="muted small">{{ $term?->name }} · {{ count($rows) }} {{ Str::plural('row', count($rows)) }}. To change it, make a new timetable on the <a href="{{ route('manage.timetable') }}">Timetable</a> page.</p>
      @endif
    </div>
    <div class="tt-buttons">
      @if ($editable)
        <a class="btn-main" href="{{ route('manage.timetable.check') }}">Check and replace →</a>
        <a class="btn-ghost" href="{{ route('manage.timetable.draft.download') }}">Download</a>
      @else
        <a class="btn-ghost" href="{{ route('manage.timetable.download') }}">Download</a>
      @endif
    </div>
  </div>

  @if ($problemCount)
    <p class="error">{{ $problemCount === 1 ? '1 row has a problem' : $problemCount.' rows have problems' }} to fix before it can replace the timetable. <a href="{{ route($page, ['day' => 'fix']) }}">See {{ $problemCount === 1 ? 'it' : 'them' }}</a>.</p>
  @endif
  @if ($warningCount)
    <p class="notice">⚠ {{ $warningCount === 1 ? '1 thing' : $warningCount.' things' }} to look at: two things in one room at once, or a block in two places. {{ $warningCount === 1 ? 'It’s' : 'They’re' }} marked below.</p>
  @endif

  {{-- What to see: a day's list, or a room's or block's week --}}
  <div class="tt-nav">
    <nav class="tt-days" aria-label="Days">
      @if ($problemCount)
        <a href="{{ route($page, ['day' => 'fix']) }}" @class(['tt-fixtab', 'on' => ! $grid && $day === 'fix'])>To fix <span>{{ $problemCount }}</span></a>
      @endif
      @foreach (TimetableRules::DAYS as $d)
        @if ($counts[$d] || in_array($d, $openDays, true))
          <a href="{{ route($page, ['day' => $d]) }}" @class(['on' => ! $grid && $day === $d]) @if (! $grid && $day === $d) aria-current="page" @endif>{{ $d }} <span>{{ $counts[$d] }}</span></a>
        @endif
      @endforeach
    </nav>
    <form method="get" action="{{ route($page) }}" class="tt-weekpick">
      <label for="tt-week" class="sr-only">See a room’s or block’s week</label>
      <select id="tt-week" name="week">
        <option value="">A room’s or block’s week…</option>
        <optgroup label="Rooms">
          @foreach ($rooms as $name)
            <option value="room:{{ $name }}" @selected($week === 'room:'.$name)>{{ $name }}</option>
          @endforeach
        </optgroup>
        @if ($blocks->isNotEmpty())
          <optgroup label="Blocks">
            @foreach ($blocks as $name)
              <option value="block:{{ $name }}" @selected($week === 'block:'.$name)>{{ $name }}</option>
            @endforeach
          </optgroup>
        @endif
      </select>
      <button type="submit" class="btn-small">Show</button>
    </form>
  </div>

  @if ($grid)
    @include('manage._timetable_week')
  @else
    @include('manage._timetable_list')
  @endif

  @if ($editable)
    @include('manage._timetable_datalists')
  @endif
  </div>
</section>
@endsection
