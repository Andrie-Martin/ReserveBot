@extends('layouts.app')
@use('App\Support\TimeSlots')

@section('title', 'Booking #'.$reservation->id)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
@endpush

@section('content')
@php
  $start = $reservation->start_time;
  $end = $reservation->end_time;
  $class = $reservation->classSchedule;
  $booker = $reservation->user;
  $rejectErrors = $errors->getBag('reject'.$reservation->id);
@endphp
<section class="card booking-card rb-booking rb-ui">
  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  <div class="rb-booking__panel">
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
    <dd class="who-cell"><x-avatar :user="$booker" /> <span>{{ $booker->name }}@if ($booker->section) · {{ $booker->section->name }}@endif @if ($booker->school_id)<span class="muted small">· {{ $booker->school_id }}</span>@endif</span></dd>

    <dt>Sent</dt>
    <dd>{{ $reservation->created_at?->format('F j, Y, g:i A') }}</dd>

    @if ($reservation->reviewer)
      <dt>{{ $reservation->status === 'Rejected' ? 'Rejected by' : 'Approved by' }}</dt>
      <dd>{{ $reservation->reviewer->name }}</dd>
    @endif

    @if (filled($reservation->status_reason))
      <dt>{{ ['Rejected' => 'Why it was rejected', 'Cancelled' => 'Why it was cancelled', 'Completed' => 'Return note'][$reservation->status] ?? 'Note' }}</dt>
      <dd>{{ $reservation->status_reason }}</dd>
    @endif
  </dl>
  </div>

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

  {{-- Lab staff and admins: hand out, take back, no-show --}}
  @if ($isStaff && in_array($reservation->status, ['Confirmed', 'InUse'], true))
    <div class="staff-box">
      <p class="review-title">Lab staff</p>
      @include('bookings._staff_actions', ['r' => $reservation, 'staff' => $staff])
    </div>
  @endif

  {{-- Lab staff and admins: cancel or move an approved booking, with a reason the rep sees --}}
  @if ($canChange)
    @php
      $moveErrors = $errors->getBag('move');
      $cancelErrors = $errors->getBag('cancel');
      $nowFrom = $start->hour * 60 + $start->minute;
      $nowTo = $end->hour * 60 + $end->minute;
    @endphp
    <details class="change-box" id="change" @if ($moveErrors->any() || $cancelErrors->any()) open @endif>
      <summary>Cancel or move this booking</summary>
      <div class="change-grid">
        <form method="post" action="{{ route('bookings.move', $reservation) }}" class="book-form">
          @csrf
          <h3>Move it</h3>
          @if ($moveErrors->any())
            <p class="error">{{ $moveErrors->first() }}</p>
          @endif
          <div class="field-row">
            <div>
              <label for="move-date" class="field-label">Day</label>
              <select id="move-date" name="date">
                @foreach ($moveDays as $day)
                  <option value="{{ $day->toDateString() }}" @selected(old('date', $start->toDateString()) === $day->toDateString())>{{ $day->format('D, M j') }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="move-from" class="field-label">From</label>
              <select id="move-from" name="from">
                @foreach ($starts as $t)
                  <option value="{{ TimeSlots::hhmm($t) }}" @selected(old('from', TimeSlots::hhmm($nowFrom)) === TimeSlots::hhmm($t))>{{ TimeSlots::clock($t) }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="move-to" class="field-label">To</label>
              <select id="move-to" name="to">
                @foreach ($ends as $t)
                  <option value="{{ TimeSlots::hhmm($t) }}" @selected(old('to', TimeSlots::hhmm($nowTo)) === TimeSlots::hhmm($t))>{{ TimeSlots::clock($t) }}</option>
                @endforeach
              </select>
            </div>
          </div>
          @if ($reservation->room_id)
            <label for="move-room" class="field-label">Room</label>
            <select id="move-room" name="room">
              @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected((int) old('room', $reservation->room_id) === $room->id)>{{ $room->name }}{{ $room->id === $reservation->room_id ? ' (now)' : '' }}</option>
              @endforeach
            </select>
          @else
            <p class="hint">Equipment only, so there’s no room to change.</p>
          @endif
          <label for="move-reason" class="field-label">Reason <span class="optional">(the rep sees this)</span></label>
          <textarea id="move-reason" name="reason" rows="2" maxlength="255" required placeholder="e.g. The projector in CEA300 is broken">{{ $moveErrors->any() ? old('reason') : '' }}</textarea>
          <p class="hint">The new room and items are checked again before anything changes.</p>
          <button type="submit" class="btn-approve">Move booking</button>
        </form>
        <form method="post" action="{{ route('bookings.staff-cancel', $reservation) }}">
          @csrf
          <h3>Cancel it</h3>
          @if ($cancelErrors->any())
            <p class="error">{{ $cancelErrors->first() }}</p>
          @endif
          <label for="staff-cancel-reason" class="field-label">Reason <span class="optional">(the rep sees this)</span></label>
          <textarea id="staff-cancel-reason" name="reason" rows="2" maxlength="255" required placeholder="e.g. The building is closed that day">{{ $cancelErrors->any() ? old('reason') : '' }}</textarea>
          <button type="submit" class="btn-reject">Cancel booking</button>
        </form>
      </div>
    </details>
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

  {{-- Every change lab staff or an admin made, with their reasons (the rep sees this too) --}}
  @if ($history->isNotEmpty())
    <div class="rb-booking__panel">
    <h2 class="history-title">History</h2>
    <ul class="history">
      @foreach ($history as $log)
        <li>
          <span class="history-when">{{ $log->created_at?->format('M j, g:i A') }}</span>
          <span><strong>{{ $log->changedBy?->name ?? 'Someone' }}</strong> {{ $log->describe() }}.@if ($log->hasOwnReason()) <span class="history-why">“{{ $log->reason }}”</span>@endif</span>
        </li>
      @endforeach
    </ul>
    </div>
  @endif

  <p class="actions">
    @if ($start->toDateString() >= today()->toDateString())
      <a class="btn-ghost" href="{{ route('rooms', ['date' => $start->toDateString()]) }}#day">See that day on Free rooms</a>
    @endif
    @if (auth()->user()->role === 'rep')
      <a class="btn-ghost" href="{{ route('bookings.mine') }}">All my bookings</a>
      <a class="btn-ghost" href="{{ route('book') }}">Make another booking</a>
    @else
      <a class="btn-ghost" href="{{ route('today', ['date' => $start->toDateString()]) }}">That day’s schedule</a>
      <a class="btn-ghost" href="{{ route('requests') }}">All requests</a>
    @endif
  </p>
</section>
@endsection
