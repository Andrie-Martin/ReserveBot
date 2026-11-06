@extends('layouts.app')
@use('App\Support\TimetableRules')

@section('title', 'Check the new timetable')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/timetable.css') }}?v={{ filemtime(public_path('css/timetable.css')) }}">
@endpush

@section('content')
@php
  $compare = $check['compare'];
  $counts = $check['counts'];
  $semesterBag = $errors->getBag('semester');
  $startsLater = $draft->term_start && $draft->term_start->isFuture();
  $added = count($compare['added']);
  $removed = count($compare['removed']);
  $changed = count($compare['changed']);
  $same = count($compare['same']);
@endphp
<section class="card manage-card rb-booking rb-manage rb-tt rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Manage</h1>
  @include('manage._tabs')
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif
  <p class="back"><a href="{{ route('manage.timetable') }}">← Timetable</a></p>
  </header>

  <div class="rb-booking__panel">
  <h2 class="edit-title">Check the new timetable</h2>
  <p class="muted">{{ $draft->sourceLabel() }} · {{ count($draft->rows) }} {{ Str::plural('row', count($draft->rows)) }}. Nothing has changed yet: the timetable in use stays until you press Replace at the bottom.</p>

  {{-- 1. Must be fixed first --}}
  @if ($check['problems'])
    @php $badRows = collect($check['problems'])->groupBy(fn ($p) => $p['row']['id']); @endphp
    <div class="tt-box tt-box-bad">
      <h3>{{ $badRows->count() === 1 ? '1 row has a problem' : $badRows->count().' rows have problems' }} to fix first</h3>
      <ul class="tt-fixlist">
        @foreach ($badRows->take(40) as $id => $list)
          @php $row = $list->first()['row']; @endphp
          <li>
            <strong>{{ $row['line'] ? 'Row '.$row['line'] : TimetableRules::what($row) }}</strong>
            <span class="muted">({{ $row['day'] ?? '?' }} {{ TimetableRules::span($row) }}{{ $row['room'] ? ', '.$row['room'] : '' }}{{ $row['code'] ? ', '.$row['code'] : '' }})</span>:
            {{ $list->pluck('text')->implode(' ') }}
            <a href="{{ route('manage.timetable.rows.edit', ['row' => $id, 'back' => 'fix']) }}">Fix it here</a>
          </li>
        @endforeach
      </ul>
      @if ($badRows->count() > 40)
        <p>…and {{ $badRows->count() - 40 }} more.</p>
      @endif
      <p class="hint">Fix them in the file and upload it again, or <a href="{{ route('manage.timetable.draft', ['day' => 'fix']) }}">fix them here on the site</a>.</p>
    </div>
  @endif

  {{-- 2. What changes --}}
  <h3 class="tt-section">What changes</h3>
  <p class="tt-total">The new timetable: <strong>{{ $counts['classes'] }}</strong> {{ Str::plural('class', $counts['classes']) }}, <strong>{{ $counts['blocked'] }}</strong> blocked {{ Str::plural('time', $counts['blocked']) }}, <strong>{{ $counts['rooms'] }}</strong> {{ Str::plural('room', $counts['rooms']) }}, <strong>{{ $counts['blocks'] }}</strong> {{ Str::plural('block', $counts['blocks']) }}.</p>
  <ul class="tt-tally">
    <li class="t-added"><strong>{{ $added }}</strong> new</li>
    <li class="t-removed"><strong>{{ $removed }}</strong> removed</li>
    <li class="t-changed"><strong>{{ $changed }}</strong> changed</li>
    <li class="t-same"><strong>{{ $same }}</strong> stay the same</li>
  </ul>
  <p class="hint">Compared with the timetable in use. A class that moves to another day, time or room counts as removed and new. Classes that stay the same keep their face-to-face answers and class-session bookings.</p>
  @php $inUse = $same + $changed + $removed; @endphp
  @if ($check['ok'] && ! $added && ! $removed && ! $changed)
    <p class="notice">It’s the same as the timetable in use. Replacing it changes only the semester, if you change that below.</p>
  @endif
  @if ($inUse >= 10 && $removed * 2 > $inUse)
    {{-- Most of it goes: right for a new semester, wrong if the file only has the changes --}}
    <p class="notice tt-mostly">⚠ This removes {{ $removed }} of the {{ $inUse }} rows of the timetable in use. That’s right for a new semester. If you only meant to add or change a few classes, start from the whole timetable instead (the template has all of it): a class that isn’t in the file is removed.</p>
  @endif

  @foreach (['added' => 'New', 'removed' => 'Removed'] as $key => $label)
    @if ($compare[$key])
      <details class="tt-changes">
        <summary>{{ $label }} ({{ count($compare[$key]) }})</summary>
        @include('manage._timetable_changes', ['rows' => TimetableRules::sort($compare[$key])])
      </details>
    @endif
  @endforeach
  @if ($compare['changed'])
    <details class="tt-changes">
      <summary>Changed ({{ $changed }})</summary>
      <ul class="tt-changed">
        @foreach ($compare['changed'] as [$was, $row])
          <li><strong>{{ $row['day'] }} {{ TimetableRules::span($row) }} · {{ $row['room'] }} · {{ TimetableRules::what($row) }}</strong>: {{ implode('; ', TimetableRules::changes($was, $row)) }}</li>
        @endforeach
      </ul>
    </details>
  @endif

  {{-- 3. Added to the lists --}}
  @if ($check['rooms']['new'] || $check['rooms']['shared'] || $check['blocks'] || $check['courses'])
    <h3 class="tt-section">Added to the lists</h3>
    <ul class="tt-adds">
      @if ($check['rooms']['new'])
        <li><strong>New {{ Str::plural('room', count($check['rooms']['new'])) }}:</strong> {{ implode(', ', $check['rooms']['new']) }}. {{ count($check['rooms']['new']) === 1 ? 'It goes' : 'They go' }} on your rooms list; set the seats on Manage → Rooms.</li>
      @endif
      @if ($check['rooms']['shared'])
        <li><strong>{{ Str::plural('Room', count($check['rooms']['shared'])) }} another department has:</strong> {{ implode(', ', $check['rooms']['shared']) }}. {{ count($check['rooms']['shared']) === 1 ? 'It’s' : 'They’re' }} shared with you, so bookings are checked against both timetables.</li>
      @endif
      @if ($check['blocks'])
        <li><strong>New {{ Str::plural('block', count($check['blocks'])) }}:</strong> {{ implode(', ', $check['blocks']) }}. Check the spelling: reps are linked to blocks, and a misspelled block gets no reminders.</li>
      @endif
      @if ($check['courses'])
        <li><strong>New {{ Str::plural('course', count($check['courses'])) }}:</strong> {{ implode(', ', $check['courses']) }}.</li>
      @endif
    </ul>
  @endif

  {{-- 4. Reps left without classes --}}
  @if ($check['orphans'])
    <h3 class="tt-section">Reps whose block has no class in it</h3>
    <ul class="tt-adds">
      @foreach ($check['orphans'] as $block => $names)
        <li><strong>{{ $block }}:</strong> {{ implode(', ', $names) }}</li>
      @endforeach
    </ul>
    <p class="hint">They won’t get class reminders, and can’t book class sessions. If their block changed for the new semester (a new year level), an admin can change it on Accounts after you save.</p>
  @endif

  {{-- 5. Worth a look --}}
  @if ($check['warnings'])
    <h3 class="tt-section">Worth a look ({{ count($check['warnings']) }})</h3>
    <p class="hint tt-hint-top">These don’t stop you from saving.</p>
    <ul class="tt-warnings">
      @foreach (array_slice($check['warnings'], 0, 60) as $warning)
        <li>⚠ {{ $warning['text'] }}</li>
      @endforeach
    </ul>
    @if (count($check['warnings']) > 60)
      <p class="hint">…and {{ count($check['warnings']) - 60 }} more.</p>
    @endif
  @endif

  {{-- 6. Bookings --}}
  @if ($check['clashes'] || $check['sessions']->isNotEmpty())
    <h3 class="tt-section">Bookings to look at</h3>
    @if ($check['clashes'])
      <p class="tt-text">{{ count($check['clashes']) === 1 ? 'This upcoming booking overlaps' : 'These upcoming bookings overlap' }} a class of the new timetable in the same room. {{ count($check['clashes']) === 1 ? 'It’s' : 'They’re' }} kept: after you save, move {{ count($check['clashes']) === 1 ? 'it' : 'them' }} to another room or time, or cancel {{ count($check['clashes']) === 1 ? 'it' : 'them' }}. Manage → Timetable lists {{ count($check['clashes']) === 1 ? 'it' : 'them' }} until then.</p>
      @include('manage._timetable_clashes', ['clashes' => $check['clashes']])
    @endif
    @if ($check['sessions']->isNotEmpty())
      <p class="tt-text">Class-session {{ Str::plural('booking', $check['sessions']->count()) }} of {{ $check['sessions']->count() === 1 ? 'a class' : 'classes' }} the new timetable removes. {{ $check['sessions']->count() === 1 ? 'It stays as a plain booking' : 'They stay as plain bookings' }}: cancel {{ $check['sessions']->count() === 1 ? 'it' : 'them' }} if {{ $check['sessions']->count() === 1 ? 'it’s' : 'they’re' }} not needed.</p>
      <ul class="mlist">
        @foreach ($check['sessions'] as $b)
          <li>
            <span class="mlist-when">{{ $b->start_time->format('D, M j') }} · {{ $b->start_time->format('g:i A') }}</span>
            <span class="mlist-what">{{ $b->room?->name }} · {{ $b->classSchedule?->course?->code ?? 'Class' }} · {{ $b->user->name }}@if ($b->user->section) ({{ $b->user->section->name }})@endif</span>
            <a href="{{ route('bookings.show', $b) }}">Open<span class="sr-only"> booking {{ $b->id }}</span></a>
          </li>
        @endforeach
      </ul>
    @endif
  @endif

  </div>

  {{-- 7. The semester, then replace --}}
  <div class="rb-booking__panel">
  <form method="post" action="{{ route('manage.timetable.apply') }}" class="mform tt-final" id="semester">
    @csrf
    <input type="hidden" name="version" value="{{ $draft->version }}">
    <fieldset>
      <legend>Semester</legend>
      <p class="hint tt-hint-top">Starting a new semester? Type its name and dates. Classes only count between these dates. Semesters are shared by every department: one with the same name gets these dates for everyone.</p>
      @include('manage._errors', ['bag' => $semesterBag])
      <div class="mgrid">
        <div>
          <label for="term-name" class="field-label">Name</label>
          <input id="term-name" name="term_name" maxlength="60" required value="{{ old('term_name', $draft->term_name) }}" placeholder="e.g. 2nd Semester AY 2026-2027">
        </div>
        <div>
          <label for="term-start" class="field-label">First day</label>
          <input id="term-start" name="term_start" type="date" required value="{{ old('term_start', $draft->term_start?->toDateString()) }}">
        </div>
        <div>
          <label for="term-end" class="field-label">Last day</label>
          <input id="term-end" name="term_end" type="date" required value="{{ old('term_end', $draft->term_end?->toDateString()) }}">
        </div>
      </div>
      @if ($term && $draft->term_name !== $term->name)
        <p class="hint">The timetable in use is for {{ $term->name }}.</p>
      @endif
      <button type="submit" class="btn-small btn-outline" formaction="{{ route('manage.timetable.semester') }}">Save the semester and check again</button>
    </fieldset>

    <div class="tt-replace">
      @if ($check['ok'])
        <button type="submit" class="btn-replace">Replace the timetable</button>
        <p class="hint">The new timetable is used right away{{ $startsLater ? ', and its classes count from '.$draft->term_start->format('F j, Y') : '' }}. The timetable in use is saved as a backup first, so you can bring it back.</p>
      @else
        <button type="submit" class="btn-replace" disabled>Replace the timetable</button>
        <p class="hint">{{ $check['problems'] ? 'Fix the problems at the top first.' : 'The new timetable is empty: add classes first.' }}</p>
      @endif
      <p class="tt-other"><a href="{{ route('manage.timetable.draft') }}">Change it on the site</a> · <a href="{{ route('manage.timetable.draft.download') }}">Download it</a> · <a href="{{ route('manage.timetable') }}">Back to Timetable</a></p>
    </div>
  </form>
  </div>
</section>
@endsection
