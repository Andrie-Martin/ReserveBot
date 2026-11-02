@extends('layouts.app')
@use('App\Support\TimeSlots')

@section('title', 'Booking #'.$reservation->id)

@section('content')
@php
  $start = $reservation->start_time;
  $end = $reservation->end_time;
  $class = $reservation->classSchedule;
  $booker = $reservation->user;
  $rejectErrors = $errors->getBag('reject'.$reservation->id);
@endphp
<section class="card booking-card">
  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
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
      <dt>{{ ['Rejected' => 'Why it was rejected', 'Cancelled' => 'Why it was cancelled'][$reservation->status] ?? 'Note' }}</dt>
      <dd>{{ $reservation->status_reason }}</dd>
    @endif
  </dl>

  {{-- Lab staff and admins: decide on a request, as it is --}}
  @if ($canReview)
    <div class="review-box">
      <p class="review-title">This booking is waiting for approval.</p>
      <div class="review-actions">
        <form method="post" action="{{ route('bookings.approve', $reservation) }}">
          @csrf
          <button type="submit" class="btn-approve">Approve</button>
        </form>
        <details class="reject" @if ($rejectErrors->any()) open @endif>
          <summary>Reject…</summary>
          <form method="post" action="{{ route('bookings.reject', $reservation) }}">
            @csrf
            <label for="reject-reason" class="field-label">Reason <span class="optional">(the rep sees this)</span></label>
            <textarea id="reject-reason" name="reason" rows="2" maxlength="255" required placeholder="e.g. The lab is closed for maintenance that day">{{ $rejectErrors->any() ? old('reason') : '' }}</textarea>
            @if ($rejectErrors->any())
              <p class="field-error">{{ $rejectErrors->first('reason') }}</p>
            @endif
            <button type="submit" class="btn-reject">Reject booking</button>
          </form>
        </details>
      </div>
    </div>
  @endif

  {{-- The rep: cancel, with a reason, until the department's deadline --}}
  @if ($canCancel)
    <details class="cancel-box" id="cancel" @if ($errors->has('reason')) open @endif>
      <summary>Cancel this booking</summary>
      <form method="post" action="{{ route('bookings.cancel', $reservation) }}">
        @csrf
        <p class="hint">You can cancel until {{ $cancelDeadline->format('D, M j, g:i A') }} ({{ $cancelHours }} before it starts).</p>
        <label for="cancel-reason" class="field-label">Why are you cancelling?</label>
        <textarea id="cancel-reason" name="reason" rows="2" maxlength="255" required placeholder="e.g. Our class was moved online">{{ old('reason') }}</textarea>
        @error('reason')
          <p class="field-error">{{ $message }}</p>
        @enderror
        <button type="submit" class="btn-reject">Cancel booking</button>
      </form>
    </details>
  @elseif ($tooLateToCancel && ! session('problem'))
    <p class="notice">It’s too late to cancel here: bookings can be cancelled until {{ $cancelHours }} before they start. Please tell the lab staff.</p>
  @endif

  <p class="actions">
    @if ($start->toDateString() >= today()->toDateString())
      <a class="btn-ghost" href="{{ route('rooms', ['date' => $start->toDateString()]) }}#day">See that day on Free rooms</a>
    @endif
    @if (auth()->user()->role === 'rep')
      <a class="btn-ghost" href="{{ route('bookings.mine') }}">All my bookings</a>
      <a class="btn-ghost" href="{{ route('book') }}">Make another booking</a>
    @else
      <a class="btn-ghost" href="{{ route('requests') }}">All requests</a>
    @endif
  </p>
</section>
@endsection
