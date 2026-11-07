@extends('layouts.app')
@use('App\Support\TimeSlots')

@section('title', 'Requests')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/operations.css') }}?v={{ filemtime(public_path('css/operations.css')) }}">
@endpush

@section('content')
<section class="card requests-card rb-booking rb-ops rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Requests</h1>
  <p class="muted">Bookings waiting for approval, soonest first. Approving keeps the room and items for the rep; rejecting frees them. Lab staff and admins can both decide.</p>
  </header>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  <div class="rb-booking__panel">
  <h2>Waiting <span class="count">{{ $waiting->count() }}</span></h2>
  @forelse ($waiting as $r)
    @php
      $start = $r->start_time;
      $end = $r->end_time;
      $span = TimeSlots::span($start->hour * 60 + $start->minute, $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440);
      $bag = $errors->getBag('reject'.$r->id);
    @endphp
    <article class="req" id="req-{{ $r->id }}">
      <div class="req-main">
        <p class="req-when"><strong>{{ $start->format('D, M j') }}</strong> · {{ $span }}
          @if ($start->isPast())<span class="tag-warn">Already started</span>@elseif ($start->isToday())<span class="tag-today">Today</span>@endif
        </p>
        <p class="req-title">{{ $r->purpose }}</p>
        <dl class="req-facts">
          <dt>Booked by</dt><dd class="who-cell"><x-avatar :user="$r->user" /> <span class="who-text"><span class="who-name">{{ $r->user->name }}@if ($r->user->section) · {{ $r->user->section->name }}@endif</span>@if ($r->user->school_id)<span class="who-id">{{ $r->user->school_id }}</span>@endif</span></dd>
          <dt>Needs</dt><dd>{{ $r->needLabel() }}</dd>
          <dt>Room</dt><dd>{{ $r->room?->name ?? 'No room' }}@if ($r->classSchedule) <span class="muted">({{ $r->classSchedule->course?->code ?? 'Class' }} class)</span>@endif</dd>
          @if ($r->hasEquipment())
            <dt>Items</dt><dd>{{ $r->itemsText() }}</dd>
          @endif
          <dt>Sent</dt><dd>{{ $r->created_at?->format('M j, g:i A') }}</dd>
        </dl>
      </div>
      <div class="req-actions">
        <form method="post" action="{{ route('bookings.approve', $r) }}">
          @csrf
          <button type="submit" class="btn-approve">Approve<span class="sr-only"> booking #{{ $r->id }}</span></button>
        </form>
        <details class="reject" @if ($bag->any()) open @endif>
          <summary>Reject…</summary>
          <form method="post" action="{{ route('bookings.reject', $r) }}">
            @csrf
            <label for="reason-{{ $r->id }}" class="field-label">Reason <span class="optional">(the rep sees this)</span></label>
            <textarea id="reason-{{ $r->id }}" name="reason" rows="2" maxlength="255" required placeholder="e.g. The lab is closed for maintenance that day" @if ($bag->any()) autofocus aria-invalid="true" aria-describedby="reason-{{ $r->id }}-error" @endif>{{ $bag->any() ? old('reason') : '' }}</textarea>
            @if ($bag->any())
              <p class="field-error" id="reason-{{ $r->id }}-error">{{ $bag->first('reason') }}</p>
            @endif
            <button type="submit" class="btn-reject">Reject booking</button>
          </form>
        </details>
        <a class="req-link" href="{{ route('bookings.show', $r) }}">Details</a>
      </div>
    </article>
  @empty
    <p class="empty">Nothing is waiting for approval.</p>
  @endforelse
  </div>

  @if ($decided->isNotEmpty())
    <div class="rb-booking__panel">
    <h2>Recently decided</h2>
    <ul class="decided">
      @foreach ($decided as $r)
        <li>
          <span class="status-badge st-{{ strtolower($r->status) }}">{{ $r->statusLabel() }}</span>
          <a href="{{ route('bookings.show', $r) }}">{{ $r->purpose }}</a>
          <span class="muted">· {{ $r->user->name }} · {{ $r->start_time->format('M j, g:i A') }} · by {{ $r->reviewer?->name }}</span>
          @if ($r->status === 'Rejected' && filled($r->status_reason))
            <span class="decided-why">“{{ $r->status_reason }}”</span>
          @endif
        </li>
      @endforeach
    </ul>
    </div>
  @endif
</section>
@endsection
