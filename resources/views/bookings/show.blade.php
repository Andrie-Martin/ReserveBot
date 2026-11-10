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

  <div class="rb-booking__panel booking-summary">
  <div class="booking-head">
    <div>
      <p class="eyebrow">Booking #{{ $reservation->id }} · {{ $reservation->needLabel() }}</p>
      <h1>{{ $reservation->purpose }}</h1>
    </div>
    <span class="status-badge st-{{ strtolower($reservation->status) }}">{{ $reservation->statusLabel() }}</span>
  </div>

  <dl class="bfacts">
    <div class="bfact bfact--when">
      <dt>When</dt>
      <dd>{{ $start->format('l, F j, Y') }} · {{ TimeSlots::span($start->hour * 60 + $start->minute, $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440) }}</dd>
    </div>

    @if ($class)
      <div class="bfact">
        <dt>Class</dt>
        <dd>{{ trim(($class->course?->code ?? 'Class').' '.($class->course?->title ?? '')) }} · {{ $class->section?->name }}</dd>
      </div>
    @endif

    <div class="bfact">
      <dt>Room</dt>
      <dd>{{ $reservation->room?->name ?? 'No room (equipment only)' }}</dd>
    </div>

    <div class="bfact bfact--who">
      <dt>Booked by</dt>
      <dd class="who-cell"><x-avatar :user="$booker" /> <span class="who-text"><span class="who-name">{{ $booker->displayName() }}@if ($reservation->representativeName()) · Rep: {{ $reservation->representativeName() }}@endif</span></span></dd>
    </div>

    @if ($reservation->hasEquipment())
      {{-- Who picks up the items (typed on the booking form). Lab staff check this ID when they hand the items out. --}}
      <div class="bfact bfact--borrower">
        <dt>Borrower</dt>
        @if ($reservation->hasBorrower())
          <dd>{{ $reservation->borrower_name }} · ID <span class="who-id-inline">{{ $reservation->borrower_id_number }}</span></dd>
        @else
          <dd class="muted">Not recorded (booked before a borrower was asked for)</dd>
        @endif
      </div>
    @endif

    <div class="bfact">
      <dt>Sent</dt>
      <dd>{{ $reservation->created_at?->format('F j, Y, g:i A') }}</dd>
    </div>

    @if ($reservation->reviewer)
      <div class="bfact">
        <dt>{{ $reservation->status === 'Rejected' ? 'Rejected by' : 'Approved by' }}</dt>
        <dd>{{ $reservation->reviewer->name }}</dd>
      </div>
    @endif

    @if (filled($reservation->status_reason))
      <div class="bfact bfact--note">
        <dt>{{ ['Rejected' => 'Why it was rejected', 'Cancelled' => 'Why it was cancelled', 'Completed' => 'Return note'][$reservation->status] ?? 'Note' }}</dt>
        <dd>{{ $reservation->status_reason }}</dd>
      </div>
    @endif
  </dl>

  @if ($reservation->hasEquipment())
    <section class="bequip" aria-labelledby="bequip-title">
      <h2 id="bequip-title">Equipment <span class="count">{{ $reservation->equipment->count() + (filled($reservation->other_items) ? 1 : 0) }}</span></h2>
      <ul class="chips">
        @foreach ($reservation->equipment as $item)
          <li class="chip"><span class="chip__name">{{ $item->name }}</span> <span class="chip__qty"><span class="sr-only">quantity </span>×{{ $item->pivot->qty_requested }}</span></li>
        @endforeach
        @if (filled($reservation->other_items))
          <li class="chip chip--other"><span class="chip__name">{{ $reservation->other_items }}</span> <span class="chip__note">not on the list</span></li>
        @endif
      </ul>
    </section>
  @endif
  </div>

  @php $showStaffActions = $canReview || ($isStaff && in_array($reservation->status, ['Confirmed', 'InUse'], true)) || $canChange; @endphp
  @if ($showStaffActions)
  <section class="rb-booking__panel booking-actions" aria-labelledby="booking-actions-title">
  <h2 id="booking-actions-title">Staff actions</h2>
  {{-- Lab staff and admins: decide on a request, as it is --}}
  @if ($canReview)
    <div class="review-box" id="review" role="group" aria-labelledby="review-title">
      <p class="review-title" id="review-title">This booking is waiting for approval.</p>
      <div class="review-panels">
        {{-- Two plain forms, both always visible: nothing is sent until its own button is pressed. --}}
        <form method="post" action="{{ route('bookings.approve', $reservation) }}" class="action-panel action-panel--approve" aria-labelledby="approve-title">
          @csrf
          <h3 id="approve-title">Approve booking</h3>
          <p class="hint">Confirms the room and any items for {{ $booker->displayName() }}.</p>
          <div class="action-panel__foot">
            <button type="submit" class="btn-approve">Approve booking</button>
          </div>
        </form>
        <form method="post" action="{{ route('bookings.reject', $reservation) }}" class="action-panel action-panel--reject" aria-labelledby="reject-title">
          @csrf
          <h3 id="reject-title">Reject booking</h3>
          <div class="action-panel__field">
            <label for="reject-reason" class="field-label">Reason <span class="optional">(required; the rep sees this)</span></label>
            <textarea id="reject-reason" name="reason" rows="2" maxlength="255" required placeholder="e.g. The lab is closed for maintenance that day" @if ($rejectErrors->any()) autofocus aria-invalid="true" aria-describedby="reject-reason-error" @endif>{{ $rejectErrors->any() ? old('reason') : '' }}</textarea>
            @if ($rejectErrors->any())
              <p class="field-error" id="reject-reason-error">{{ $rejectErrors->first('reason') }}</p>
            @endif
          </div>
          <div class="action-panel__foot">
            <button type="submit" class="btn-reject">Reject booking</button>
          </div>
        </form>
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

  {{-- Lab staff and admins: move or cancel an approved booking, with a reason the rep sees. One action at a time. --}}
  @if ($canChange)
    @php
      $moveErrors = $errors->getBag('move');
      $cancelErrors = $errors->getBag('cancel');
      $nowFrom = $start->hour * 60 + $start->minute;
      $nowTo = $end->hour * 60 + $end->minute;
      $manyRooms = count($rooms) > 8;
    @endphp
    <div class="change-actions" id="change">
      <p class="review-title" id="change-title">Change this booking</p>
      <div class="change-bar" role="group" aria-labelledby="change-title">
        <a class="btn-ghost change-toggle" href="#move-panel" data-rb-reveal="move-panel">Move booking</a>
        <a class="btn-ghost btn-ghost--danger change-toggle" href="#cancel-panel" data-rb-reveal="cancel-panel">Cancel booking</a>
      </div>

      <form method="post" action="{{ route('bookings.move', $reservation) }}" id="move-panel" data-rb-once data-rb-reveal-group="change" @class(['change-panel', 'rb-reveal', 'is-open' => $moveErrors->any()])>
        @csrf
        <h3>Move booking</h3>
        @if ($moveErrors->any())
          <p class="error" role="alert">{{ $moveErrors->first() }}</p>
        @endif
        <div class="change-fields">
          <div>
            <label for="move-date" class="field-label">Day</label>
            <select id="move-date" name="date" data-rb-picker data-rb-focus>
              @foreach ($moveDays as $day)
                <option value="{{ $day->toDateString() }}" @selected(old('date', $start->toDateString()) === $day->toDateString())>{{ $day->format('D, M j') }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="move-from" class="field-label">From</label>
            <select id="move-from" name="from" data-rb-picker>
              @foreach ($starts as $t)
                <option value="{{ TimeSlots::hhmm($t) }}" @selected(old('from', TimeSlots::hhmm($nowFrom)) === TimeSlots::hhmm($t))>{{ TimeSlots::clock($t) }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="move-to" class="field-label">To</label>
            <select id="move-to" name="to" data-rb-picker>
              @foreach ($ends as $t)
                <option value="{{ TimeSlots::hhmm($t) }}" @selected(old('to', TimeSlots::hhmm($nowTo)) === TimeSlots::hhmm($t))>{{ TimeSlots::clock($t) }}</option>
              @endforeach
            </select>
          </div>
          @if ($reservation->room_id)
            <div>
              <label for="move-room" class="field-label">Room</label>
              <select id="move-room" name="room" {!! $manyRooms ? 'data-rb-picker="search" data-rb-search-label="Search rooms" data-rb-empty="No room matches that search."' : 'data-rb-picker' !!}>
                @foreach ($rooms as $room)
                  <option value="{{ $room->id }}" @selected((int) old('room', $reservation->room_id) === $room->id)>{{ $room->name }}{{ $room->id === $reservation->room_id ? ' (now)' : '' }}</option>
                @endforeach
              </select>
            </div>
          @endif
        </div>
        @unless ($reservation->room_id)
          <p class="hint">Equipment only, so there’s no room to change.</p>
        @endunless
        <div>
          <label for="move-reason" class="field-label">Reason <span class="optional">(the rep sees this)</span></label>
          <textarea id="move-reason" name="reason" rows="3" maxlength="255" required placeholder="e.g. The projector in CEA300 is broken">{{ $moveErrors->any() ? old('reason') : '' }}</textarea>
          <p class="hint">The new room and items are checked again before anything changes.</p>
        </div>
        <div class="change-footer">
          <button type="submit" class="btn-approve">Move booking</button>
          <a class="btn-ghost" href="#change" data-rb-reveal-close>Back</a>
        </div>
      </form>

      <form method="post" action="{{ route('bookings.staff-cancel', $reservation) }}" id="cancel-panel" data-rb-once data-rb-reveal-group="change" @class(['change-panel', 'change-panel--cancel', 'rb-reveal', 'is-open' => $cancelErrors->any()])>
        @csrf
        <h3>Cancel booking</h3>
        <p class="hint">Cancelling frees the room and any items, and tells the rep why.</p>
        @if ($cancelErrors->any())
          <p class="error" role="alert">{{ $cancelErrors->first() }}</p>
        @endif
        <div>
          <label for="staff-cancel-reason" class="field-label">Reason <span class="optional">(the rep sees this)</span></label>
          <textarea id="staff-cancel-reason" name="reason" data-rb-focus rows="3" maxlength="255" required placeholder="e.g. The building is closed that day">{{ $cancelErrors->any() ? old('reason') : '' }}</textarea>
        </div>
        {{-- Confirmation only: it has no name, so it is never sent; the browser refuses to submit until it is ticked. --}}
        <label class="check change-confirm" for="staff-cancel-confirm"><input type="checkbox" id="staff-cancel-confirm" required> Yes, cancel this booking for {{ $booker->displayName() }}.</label>
        <div class="change-footer">
          <button type="submit" class="btn-reject btn-reject--solid">Cancel booking</button>
          <a class="btn-ghost" href="#change" data-rb-reveal-close>Back</a>
        </div>
      </form>
    </div>
  @endif
  </section>
  @endif

  {{-- The rep: cancel, with a reason, until the department's deadline --}}
  @if ($canCancel)
    <section class="rb-booking__panel cancel-card" id="cancel" aria-labelledby="cancel-title">
      <div class="cancel-card__info">
        <h2 id="cancel-title">Cancel this booking</h2>
        <p class="cancel-card__deadline">You can cancel until <strong>{{ $cancelDeadline->format('D, M j, g:i A') }}</strong></p>
        <p class="hint">That is {{ $cancelHours }} before it starts. Cancelling frees the room and any items, and the reason you give is kept with the booking.</p>
      </div>
      <form method="post" action="{{ route('bookings.cancel', $reservation) }}" class="cancel-card__form">
        @csrf
        <div>
          <label for="cancel-reason" class="field-label">Why are you cancelling? <span class="optional">(required)</span></label>
          <textarea id="cancel-reason" name="reason" rows="2" maxlength="255" required placeholder="e.g. Our class was moved online" @error('reason') autofocus aria-invalid="true" aria-describedby="cancel-reason-error" @enderror>{{ old('reason') }}</textarea>
          @error('reason')
            <p class="field-error" id="cancel-reason-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="action-panel__foot">
          <button type="submit" class="btn-reject">Cancel booking</button>
        </div>
      </form>
    </section>
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
