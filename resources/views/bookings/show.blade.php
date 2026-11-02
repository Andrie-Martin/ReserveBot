@extends('layouts.app')
@use('App\Support\TimeSlots')

@section('title', 'Booking #'.$reservation->id)

@section('content')
@php
  $start = $reservation->start_time;
  $end = $reservation->end_time;
  $class = $reservation->classSchedule;
  $booker = $reservation->user;
@endphp
<section class="card booking-card">
  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif

  <div class="booking-head">
    <div>
      <p class="eyebrow">Booking #{{ $reservation->id }}</p>
      <h1>{{ $reservation->needLabel() }}</h1>
    </div>
    <span class="status-badge st-{{ strtolower($reservation->status) }}">{{ $reservation->statusLabel() }}</span>
  </div>

  <dl class="facts">
    <dt>When</dt>
    <dd>{{ $start->format('l, F j, Y') }} · {{ TimeSlots::span($start->hour * 60 + $start->minute, $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440) }}</dd>

    @if ($class)
      <dt>Class</dt>
      <dd>{{ trim(($class->course?->code ?? 'Class').' '.($class->course?->title ?? '')) }} · {{ $class->section?->name }}</dd>
    @endif

    <dt>Room</dt>
    <dd>{{ $reservation->room?->name ?? 'No room (equipment only)' }}</dd>

    @if ($reservation->hasEquipment())
      <dt>Equipment</dt>
      <dd>
        <ul class="item-list">
          @foreach ($reservation->equipment as $item)
            <li>{{ $item->name }} <span class="qty">×{{ $item->pivot->qty_requested }}</span></li>
          @endforeach
          @if (filled($reservation->other_items))
            <li>{{ $reservation->other_items }} <span class="muted small">(not on the list)</span></li>
          @endif
        </ul>
      </dd>
    @endif

    <dt>Reason</dt>
    <dd>{{ $reservation->purpose }}</dd>

    <dt>Booked by</dt>
    <dd>{{ $booker->name }}@if ($booker->section) · {{ $booker->section->name }}@endif</dd>

    <dt>Sent</dt>
    <dd>{{ $reservation->created_at?->format('F j, Y, g:i A') }}</dd>

    @if ($reservation->reviewer)
      <dt>{{ $reservation->status === 'Rejected' ? 'Rejected by' : 'Approved by' }}</dt>
      <dd>{{ $reservation->reviewer->name }}</dd>
    @endif

    @if (filled($reservation->status_reason))
      <dt>Note</dt>
      <dd>{{ $reservation->status_reason }}</dd>
    @endif
  </dl>

  <p class="actions">
    <a class="btn-ghost" href="{{ route('rooms', ['date' => $start->toDateString()]) }}#day">See that day on Free rooms</a>
    @if (auth()->user()->role === 'rep')
      <a class="btn-ghost" href="{{ route('book') }}">Make another booking</a>
    @endif
  </p>
</section>
@endsection
