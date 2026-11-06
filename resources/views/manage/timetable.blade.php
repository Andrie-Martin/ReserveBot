@extends('layouts.app')

@section('title', 'Timetable')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/timetable.css') }}?v={{ filemtime(public_path('css/timetable.css')) }}">
@endpush

@section('content')
@php $inUse = $counts['classes'] + $counts['blocked']; @endphp
<section class="card manage-card rb-booking rb-manage rb-tt rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Manage</h1>
  @include('manage._tabs')
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  <p class="muted">{{ $department->name }}’s timetable: the classes and blocked times that hold rooms each week. When a new semester starts, or classes move, make a new timetable here. It replaces this one as soon as you save it, and this one is kept as a backup.</p>
  </header>

  {{-- The timetable in use --}}
  <div class="tt-now">
    <div>
      <p class="eyebrow">In use now</p>
      @if ($inUse)
        <p class="tt-now-term">{{ $term?->name }}</p>
        @if ($term)
          <p class="tt-now-dates">{{ $term->start_date->format('F j, Y') }} to {{ $term->end_date->format('F j, Y') }} <span class="muted">· classes count between these dates</span></p>
        @endif
        <p class="tt-now-counts">{{ $counts['classes'] }} {{ Str::plural('class', $counts['classes']) }} · {{ $counts['blocked'] }} blocked {{ Str::plural('time', $counts['blocked']) }} · {{ $counts['rooms'] }} {{ Str::plural('room', $counts['rooms']) }} · {{ $counts['blocks'] }} {{ Str::plural('block', $counts['blocks']) }}</p>
      @else
        <p class="tt-now-term">No timetable yet</p>
        <p class="tt-now-counts">Upload one below, or make it on the site.</p>
      @endif
    </div>
    @if ($inUse)
      <div class="tt-buttons">
        <a class="btn-ghost" href="{{ route('manage.timetable.view') }}">See it by day or room</a>
        <a class="btn-ghost" href="{{ route('manage.timetable.download') }}">Download it</a>
      </div>
    @endif
  </div>

  {{-- Bookings kept when the timetable changed that overlap one of its classes --}}
  @if ($clashes)
    <div class="tt-attention" id="clashes">
      <h2>{{ count($clashes) }} upcoming {{ Str::plural('booking', count($clashes)) }} {{ count($clashes) === 1 ? 'overlaps' : 'overlap' }} the timetable</h2>
      <p>{{ count($clashes) === 1 ? 'It holds a room' : 'They hold rooms' }} while the timetable has a class there. That happens when the timetable changes after a booking is made. Open {{ count($clashes) === 1 ? 'it' : 'each one' }} to move it to another room or time, or to cancel it.</p>
      @include('manage._timetable_clashes', ['clashes' => $clashes])
    </div>
  @endif

  {{-- The new timetable in progress --}}
  @if ($draft)
    <div class="tt-draft">
      <p class="eyebrow">New timetable in progress <span class="tt-tag">not in use yet</span></p>
      <p class="tt-draft-title">{{ $draft->sourceLabel() }}</p>
      <p class="tt-draft-facts">
        {{ count($draft->rows) }} {{ Str::plural('row', count($draft->rows)) }}@if ($draft->term_name) · for {{ $draft->term_name }}@endif
        @if ($draftProblems) · <strong class="tt-bad">{{ $draftProblems }} {{ Str::plural('row', $draftProblems) }} to fix</strong>@endif
      </p>
      <p class="hint">Started by {{ $draft->starter?->name ?? 'someone' }}, {{ $draft->created_at->format('M j, g:i A') }}@if ($draft->updated_at->gt($draft->created_at)) · last change by {{ $draft->updater?->name ?? 'someone' }}, {{ $draft->updated_at->format('M j, g:i A') }}@endif</p>
      <div class="tt-buttons">
        <a class="btn-main" href="{{ route('manage.timetable.check') }}">Check and replace →</a>
        <a class="btn-ghost" href="{{ route('manage.timetable.draft') }}">Change it on the site</a>
        <a class="btn-ghost" href="{{ route('manage.timetable.draft.download') }}">Download it</a>
      </div>
      <details class="tt-discard">
        <summary>Discard it…</summary>
        <form method="post" action="{{ route('manage.timetable.discard') }}">
          @csrf
          @method('DELETE')
          <p class="hint">This throws away the new timetable. The timetable in use stays as it is.</p>
          <button type="submit" class="btn-small btn-danger">Discard the new timetable</button>
        </form>
      </details>
    </div>
  @endif

  {{-- Two ways to make a new one --}}
  <div class="rb-booking__panel">
  <h2>{{ $draft ? 'Or start over' : 'Make a new timetable' }}</h2>
  <div class="tt-ways">
    <section class="tt-way">
      <h3><span class="tt-num">1</span> Upload an Excel file</h3>
      <ol class="tt-steps">
        <li><a href="{{ route('manage.timetable.download') }}">Download the template</a>: the timetable in use as {{ $canXlsx ? 'an Excel file' : 'a CSV file' }}, one row per class.</li>
        <li>Change it in Excel or Google Sheets: add, change or delete rows. Keep row 1 (the column names).</li>
        <li>Upload it here. Nothing changes until you check it and press Replace.</li>
      </ol>
      <form method="post" action="{{ route('manage.timetable.upload') }}" enctype="multipart/form-data" class="mform tt-upload">
        @csrf
        @include('manage._errors', ['bag' => $errors->getBag('upload')])
        <label for="tt-file" class="field-label">Excel or CSV file</label>
        <input id="tt-file" name="file" type="file" required accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
        <button type="submit" class="btn-small">Upload and check</button>
        @if ($draft)
          <p class="hint">It takes the place of the new timetable in progress.</p>
        @endif
      </form>
    </section>
    <section class="tt-way">
      <h3><span class="tt-num">2</span> Change it on the site</h3>
      <p>Start from a copy of the timetable in use. Add, change or delete classes day by day, and see each room’s week.</p>
      @if ($draft)
        <a class="btn-ghost" href="{{ route('manage.timetable.draft') }}">Continue the one in progress</a>
      @else
        <form method="post" action="{{ route('manage.timetable.start') }}">
          @csrf
          <button type="submit" class="btn-small">Start from the timetable in use</button>
        </form>
      @endif
    </section>
  </div>
  @unless ($canXlsx)
    <p class="notice tt-zip">This computer’s PHP can’t open or make Excel (.xlsx) files yet: its zip extension is off. Downloads are CSV files for now (Excel opens them too), and you can upload CSV files. To turn .xlsx on, see “Timetable” in the README.</p>
  @endunless
  </div>

  {{-- Backups --}}
  <div class="rb-booking__panel">
  <h2 id="backups">Backups</h2>
  <p class="muted">Each time the timetable is replaced, the one it replaced is saved here. Bringing one back works like a new timetable: you see what changes first, and the timetable in use is saved as a backup too.</p>
  @if ($backups->isEmpty())
    <p class="empty">None yet. The first one is saved when the timetable is first replaced.</p>
  @else
    <ul class="tt-backups">
      @foreach ($backups as $backup)
        <li>
          <div class="tt-backup-main">
            <strong>{{ $backup->label() }}</strong>
            <span>{{ $backup->term_name ?? 'No semester' }} · {{ $backup->class_count }} {{ Str::plural('class', $backup->class_count) }}@if ($backup->blocked_count) · {{ $backup->blocked_count }} blocked @endif</span>
            <span class="tt-backup-why">{{ $backup->reason }}@if ($backup->creator), by {{ $backup->creator->name }}@endif</span>
          </div>
          <div class="tt-backup-actions">
            <a class="btn-ghost" href="{{ route('manage.timetable.backups.download', $backup) }}">Download<span class="sr-only"> the backup of {{ $backup->label() }}</span></a>
            @if ($draft)
              <details class="tt-restore">
                <summary>Bring back…<span class="sr-only"> the backup of {{ $backup->label() }}</span></summary>
                <form method="post" action="{{ route('manage.timetable.backups.restore', $backup) }}">
                  @csrf
                  <p class="hint">It takes the place of the new timetable in progress. You’ll check it before anything changes.</p>
                  <button type="submit" class="btn-small">Check this backup</button>
                </form>
              </details>
            @else
              <form method="post" action="{{ route('manage.timetable.backups.restore', $backup) }}">
                @csrf
                <button type="submit" class="btn-small btn-outline">Bring back…<span class="sr-only"> the backup of {{ $backup->label() }}</span></button>
              </form>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
  @endif
  </div>
</section>
@endsection
