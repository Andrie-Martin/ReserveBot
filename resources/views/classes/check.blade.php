{{-- Phase 10: opened from a button in a reminder email. Works without logging in, until the class ends. --}}
@extends('layouts.app')

@section('title', 'Is '.$name.' face-to-face?')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/check.css') }}?v={{ filemtime(public_path('css/check.css')) }}">
@endpush

@section('content')
<section class="card narrow check-card rb-booking rb-check rb-ui">
  <p class="eyebrow">Face-to-face check</p>
  <h1>{{ $name }}@if ($class->course?->title) <span class="check-title">{{ $class->course->title }}</span>@endif</h1>
  <p class="check-facts">
    {{ $start->format('l, F j') }} · {{ $span }}<br>
    {{ $class->room->name }}@if ($class->section) · {{ $class->section->name }}@endif
  </p>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  <p class="check-now">Now: <span class="st {{ \App\Services\ClassChecks::BADGES[$state] }}">{{ $label }}</span></p>

  @if ($open)
    {{-- What is saved decides which card is marked "Your answer". The ?answer= of the email button only marks a card while nothing is saved
         (a cue to press it), so a leftover value from an earlier click can't contradict the saved answer. --}}
    @php
      $saved = in_array($state, ['f2f', 'online'], true) ? $state : null;
      $chosen = $saved === null ? $picked : null;
    @endphp
    {{-- The answer is saved only when a button is pressed, so link checkers in email apps can't answer by opening the link --}}
    <form method="post" action="{{ request()->fullUrl() }}" class="check-choice">
      @csrf
      <button type="submit" name="answer" value="f2f" @class(['btn-f2f', 'picked' => $chosen === 'f2f', 'current' => $saved === 'f2f'])>
        Face-to-face<span class="check-sub">keep {{ $class->room->name }}</span>
        @if ($saved === 'f2f')<span class="check-tag">Your answer</span>@elseif ($chosen === 'f2f')<span class="check-tag check-tag--cue">Chosen in your email</span>@endif
      </button>
      <button type="submit" name="answer" value="online" @class(['btn-online', 'picked' => $chosen === 'online', 'current' => $saved === 'online'])>
        Online or no class<span class="check-sub">free {{ $class->room->name }}</span>
        @if ($saved === 'online')<span class="check-tag">Your answer</span>@elseif ($chosen === 'online')<span class="check-tag check-tag--cue">Chosen in your email</span>@endif
      </button>
    </form>
    <p class="hint">Answering for {{ $rep->name }}. You can change it until the class ends. If the room was freed, “Face-to-face” takes it back as long as nobody has booked it.</p>
  @elseif ($state === 'booked')
    <p class="hint">This class meeting is booked as a class session, so it keeps its room. To change that, open the booking from My bookings.</p>
  @elseif (! $isRep)
    <p class="hint">This link was for {{ $rep->name }}, who isn’t this block’s rep anymore.</p>
  @else
    <p class="hint">This class has ended.</p>
  @endif
</section>
@endsection
