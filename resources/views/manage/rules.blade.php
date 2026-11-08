@extends('layouts.app')
@use('App\Http\Controllers\ManageRulesController')

@section('title', 'Booking rules')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
@php
  $bag = $errors->getBag('rules');
  // Old input is only meaningful when THIS form failed. A failed Semester-dates save also flashes old input, but it must not
  // reset the separate checkboxes here (an unchecked box submits nothing, so "no old value" would read as "unticked").
  $days = $bag->any() ? (array) old('open_days', []) : $config->openDays();
  $value = fn ($field, $current) => $bag->any() ? old($field, $current) : $current;
@endphp
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Booking rules</h1>
  @include('manage._tabs')

  <p class="muted">{{ auth()->user()->department->name }}’s booking rules. New bookings follow them as soon as you save; bookings already made stay as they are.</p>
  </header>

  <div class="rb-booking__panel">
  <form method="post" action="{{ route('manage.rules.update') }}" class="mform rules-form">
    @csrf
    @method('PUT')
    @php
      $rulesMap = ['open_days' => 'day-'.ManageRulesController::DAYS[0], 'business_hours_start' => 'opens', 'business_hours_end' => 'closes', 'slot_minutes' => 'step', 'min_booking_minutes' => 'min', 'max_booking_minutes' => 'max', 'max_days_ahead' => 'ahead', 'min_notice_minutes' => 'notice', 'cancel_cutoff_minutes' => 'cancel', 'no_show_grace_minutes' => 'late'];
      $rulesHas = fn (string $field) => collect($bag->keys())->contains(fn ($key) => $key === $field || str_starts_with($key, $field.'.'));
      // A long form: after a refused save the error summary itself takes focus (so it is announced and in view); the fields it names are marked.
      $rulesInvalid = fn (string $field) => $rulesHas($field) ? 'aria-invalid="true" aria-describedby="rules-errors"' : '';
    @endphp
    @if ($bag->any())<div id="rules-errors" tabindex="-1" autofocus>@include('manage._errors', ['bag' => $bag])</div>@endif

    <fieldset>
      <legend>Approval</legend>
      <label class="check"><input type="checkbox" name="requires_approval" value="1" @checked($bag->any() ? old('requires_approval') : $config->requires_approval)> New bookings wait for approval from lab staff or an admin</label>
      <p class="hint">Untick to confirm bookings right away.</p>
    </fieldset>

    <fieldset>
      <legend>Open days</legend>
      <div class="day-checks">
        @foreach (ManageRulesController::DAYS as $day)
          <label class="check"><input type="checkbox" id="day-{{ $day }}" name="open_days[]" value="{{ $day }}" @if ($loop->first) {!! $rulesInvalid('open_days') !!} @endif @checked(in_array($day, (array) $days, true))> {{ $day }}</label>
        @endforeach
      </div>
    </fieldset>

    <fieldset>
      <legend>Hours</legend>
      <div class="mgrid">
        <div>
          <label for="opens" class="field-label">Opens at</label>
          <input id="opens" name="business_hours_start" type="time" step="900" required {!! $rulesInvalid('business_hours_start') !!} value="{{ $value('business_hours_start', substr($config->business_hours_start, 0, 5)) }}">
        </div>
        <div>
          <label for="closes" class="field-label">Closes at</label>
          <input id="closes" name="business_hours_end" type="time" step="900" required {!! $rulesInvalid('business_hours_end') !!} value="{{ $value('business_hours_end', substr($config->business_hours_end, 0, 5)) }}">
        </div>
        <div>
          <label for="step" class="field-label">Time steps</label>
          <select id="step" name="slot_minutes" {!! $rulesInvalid('slot_minutes') !!}>
            @foreach ([15, 30, 60] as $minutes)
              <option value="{{ $minutes }}" @selected((int) $value('slot_minutes', $config->slot_minutes) === $minutes)>{{ $minutes }} minutes</option>
            @endforeach
          </select>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Booking length <span class="optional">(a class session keeps its own length)</span></legend>
      <div class="mgrid">
        <div>
          <label for="min" class="field-label">Shortest <span class="optional">(minutes)</span></label>
          <input id="min" name="min_booking_minutes" type="number" min="15" max="720" required {!! $rulesInvalid('min_booking_minutes') !!} value="{{ $value('min_booking_minutes', $config->min_booking_minutes) }}">
        </div>
        <div>
          <label for="max" class="field-label">Longest <span class="optional">(minutes)</span></label>
          <input id="max" name="max_booking_minutes" type="number" min="15" max="720" required {!! $rulesInvalid('max_booking_minutes') !!} value="{{ $value('max_booking_minutes', $config->max_booking_minutes) }}">
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Timing</legend>
      <div class="mgrid">
        <div>
          <label for="ahead" class="field-label">Book up to <span class="optional">(days ahead)</span></label>
          <input id="ahead" name="max_days_ahead" type="number" min="1" max="90" required {!! $rulesInvalid('max_days_ahead') !!} value="{{ $value('max_days_ahead', $config->max_days_ahead) }}">
        </div>
        <div>
          <label for="notice" class="field-label">Book at least <span class="optional">(minutes before it starts)</span></label>
          <input id="notice" name="min_notice_minutes" type="number" min="0" max="2880" required {!! $rulesInvalid('min_notice_minutes') !!} value="{{ $value('min_notice_minutes', $config->min_notice_minutes) }}">
        </div>
        <div>
          <label for="cancel" class="field-label">Reps can cancel until <span class="optional">(minutes before it starts)</span></label>
          <input id="cancel" name="cancel_cutoff_minutes" type="number" min="0" max="2880" required {!! $rulesInvalid('cancel_cutoff_minutes') !!} value="{{ $value('cancel_cutoff_minutes', $config->cancel_cutoff_minutes) }}">
        </div>
        <div>
          <label for="late" class="field-label">Show “Late” after <span class="optional">(minutes after the start)</span></label>
          <input id="late" name="no_show_grace_minutes" type="number" min="0" max="240" required {!! $rulesInvalid('no_show_grace_minutes') !!} value="{{ $value('no_show_grace_minutes', $config->no_show_grace_minutes) }}">
        </div>
      </div>
    </fieldset>

    <div class="mform-footer"><button type="submit" class="btn-small">Save rules</button></div>
  </form>
  </div>

  <div class="rb-booking__panel">
  <h2>Semester dates</h2>
  <p class="muted">Shared by every department. Classes on the timetable only count between these dates.</p>
  @foreach ($terms as $term)
    @php
      $termBag = $errors->getBag('term'.$term->id);
      // After a refused save the first invalid date takes focus (never on an ordinary load); the summary is its description.
      $termFocus = $termBag->has('start_date') ? 'start_date' : ($termBag->has('end_date') ? 'end_date' : null);
      $termInvalid = fn (string $field) => ($termBag->has($field) ? 'aria-invalid="true" aria-describedby="term-'.$term->id.'-'.$field.'-error"' : '').($termFocus === $field ? ' autofocus' : '');
      $termMsg = fn (string $field) => $termBag->has($field) ? new \Illuminate\Support\HtmlString('<p class="field-error" id="term-'.$term->id.'-'.$field.'-error">'.e($termBag->first($field)).'</p>') : '';
    @endphp
    <form method="post" action="{{ route('manage.terms.update', $term) }}" class="mform term-form">
      @csrf
      @method('PUT')
      <p class="term-name">{{ $term->name }}</p>
      @if ($termBag->any())<div id="term-{{ $term->id }}-errors">@include('manage._errors', ['bag' => $termBag])</div>@endif
      <div class="mgrid">
        <div>
          <label for="start-{{ $term->id }}" class="field-label">First day</label>
          {!! $termMsg('start_date') !!}
          <input id="start-{{ $term->id }}" name="start_date" type="date" required {!! $termInvalid('start_date') !!} value="{{ $termBag->any() ? old('start_date') : $term->start_date->toDateString() }}">
        </div>
        <div>
          <label for="end-{{ $term->id }}" class="field-label">Last day</label>
          {!! $termMsg('end_date') !!}
          <input id="end-{{ $term->id }}" name="end_date" type="date" required {!! $termInvalid('end_date') !!} value="{{ $termBag->any() ? old('end_date') : $term->end_date->toDateString() }}">
        </div>
      </div>
      <div class="mform-footer"><button type="submit" class="btn-small">Save dates</button></div>
    </form>
  @endforeach
  </div>
</section>
@endsection
