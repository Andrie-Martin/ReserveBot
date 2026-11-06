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
    {{-- The answer is saved only when a button is pressed, so link checkers in email apps can't answer by opening the link --}}
    <form method="post" action="{{ request()->fullUrl() }}" class="check-choice">
      @csrf
      <button type="submit" name="answer" value="f2f" @class(['btn-f2f', 'picked' => $picked === 'f2f', 'current' => $state === 'f2f'])>
        Face-to-face<span>keep {{ $class->room->name }}</span>
      </button>
      <button type="submit" name="answer" value="online" @class(['btn-online', 'picked' => $picked === 'online', 'current' => $state === 'online'])>
        Online or no class<span>free {{ $class->room->name }}</span>
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
