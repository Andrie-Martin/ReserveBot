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
  {{-- Presentation grouping only: the same waiting list, in the same soonest-first order, split by whether a room is asked for.
       The category is a query parameter (?tab=room|equipment), so the links work without a script, survive refresh and Back,
       and a refused rejection (which redirects back to this URL) stays on the same category. --}}
  @php
    $requestGroups = [
      'room' => ['key' => 'room', 'title' => 'Room requests', 'hint' => 'Room only, or room and equipment', 'empty' => 'No room requests are waiting for approval.', 'items' => $waiting->filter(fn ($r) => (bool) $r->room_id)->values()],
      'equipment' => ['key' => 'equipment', 'title' => 'Equipment-only requests', 'hint' => 'Items to borrow; no room is held', 'empty' => 'No equipment-only requests are waiting for approval.', 'items' => $waiting->filter(fn ($r) => ! $r->room_id)->values()],
    ];
    $activeKey = request()->query('tab') === 'equipment' ? 'equipment' : 'room';
    // A refused rejection must show its own form even if the address lost the category.
    foreach ($requestGroups as $key => $candidate) {
      foreach ($candidate['items'] as $candidateRequest) {
        if ($errors->getBag('reject'.$candidateRequest->id)->any()) { $activeKey = $key; }
      }
    }
    $group = $requestGroups[$activeKey];
    $otherGroup = $requestGroups[$activeKey === 'room' ? 'equipment' : 'room'];
  @endphp
  <nav class="rb-subnav req-tabs" aria-label="Request categories">
    @foreach ($requestGroups as $key => $tab)
      <a class="rb-subnav__link" id="req-tab-{{ $key }}" href="{{ route('requests', $key === 'room' ? [] : ['tab' => $key]) }}" @if ($key === $activeKey) aria-current="page" @endif>{{ $tab['title'] }} <span class="count">{{ $tab['items']->count() }}</span><span class="sr-only"> waiting</span></a>
    @endforeach
  </nav>
  <section class="req-group req-group--{{ $group['key'] }}" aria-labelledby="req-tab-{{ $group['key'] }}">
      <p class="req-group__hint">{{ $group['hint'] }}. Soonest first. Grouping does not change who can approve or any booking rule.</p>
      @forelse ($group['items'] as $r)
      @php
        $start = $r->start_time;
        $end = $r->end_time;
        $span = TimeSlots::span($start->hour * 60 + $start->minute, $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440);
        $bag = $errors->getBag('reject'.$r->id);
      @endphp
      <article class="req" id="req-{{ $r->id }}" aria-labelledby="req-{{ $r->id }}-title">
        <header class="req-head">
          <div class="req-headline">
            <h3 class="req-title" id="req-{{ $r->id }}-title">{{ $r->purpose }}</h3>
            <p class="req-when"><strong>{{ $start->format('D, M j') }}</strong> · {{ $span }}
              @if ($start->isPast())<span class="tag-warn">Already started</span>@elseif ($start->isToday())<span class="tag-today">Today</span>@endif
            </p>
          </div>
          <span class="status-badge st-{{ strtolower($r->status) }}">{{ $r->statusLabel() }}</span>
        </header>
        <dl class="req-facts">
          <div class="req-fact req-fact--who"><dt>Booked by</dt><dd class="who-cell"><x-avatar :user="$r->user" /> <span class="who-text"><span class="who-name">{{ $r->user->displayName() }}@if ($r->representativeName()) · Rep: {{ $r->representativeName() }}@endif</span></span></dd></div>
          <div class="req-fact req-fact--room"><dt>Room</dt><dd>{{ $r->room?->name ?? 'No room' }}@if ($r->classSchedule) <span class="muted">({{ $r->classSchedule->course?->code ?? 'Class' }} class)</span>@endif</dd></div>
          <div class="req-fact"><dt>Needs</dt><dd>{{ $r->needLabel() }}</dd></div>
          @if ($r->hasEquipment())
            <div class="req-fact req-fact--items"><dt>Items</dt><dd>{{ $r->itemsText() }}</dd></div>
            <div class="req-fact req-fact--borrower"><dt>Borrower</dt><dd>@if ($r->hasBorrower()){{ $r->borrower_name }} · ID <span class="who-id-inline">{{ $r->borrower_id_number }}</span>@else<span class="muted">Not recorded</span>@endif</dd></div>
          @endif
          <div class="req-fact"><dt>Sent</dt><dd>{{ $r->created_at?->format('M j, g:i A') }}</dd></div>
        </dl>
        <div class="req-actions">
          <form method="post" action="{{ route('bookings.approve', $r) }}">
            @csrf
            <button type="submit" class="btn-approve">Approve<span class="sr-only"> booking #{{ $r->id }}</span></button>
          </form>
          <a class="btn-reject" href="#reject-{{ $r->id }}" data-rb-reveal="reject-{{ $r->id }}">Reject<span class="sr-only"> booking #{{ $r->id }}</span></a>
          <a class="btn-ghost req-link" href="{{ route('bookings.show', [$r, 'from' => 'requests']) }}">Details<span class="sr-only"> for booking #{{ $r->id }}</span></a>
        </div>
        {{-- Full width beneath the booking: opens in place (script) or by #fragment (no script); a refused reason keeps it open. --}}
        <form method="post" action="{{ route('bookings.reject', $r) }}" id="reject-{{ $r->id }}" @class(['req-reject', 'rb-reveal', 'is-open' => $bag->any()])>
          @csrf
          <label for="reason-{{ $r->id }}" class="field-label">Reason <span class="optional">(the rep sees this)</span></label>
          <textarea id="reason-{{ $r->id }}" name="reason" rows="3" maxlength="255" required placeholder="e.g. The lab is closed for maintenance that day" @if ($bag->any()) autofocus aria-invalid="true" aria-describedby="reason-{{ $r->id }}-error" @endif>{{ $bag->any() ? old('reason') : '' }}</textarea>
          @if ($bag->any())
            <p class="field-error" id="reason-{{ $r->id }}-error">{{ $bag->first('reason') }}</p>
          @endif
          <div class="req-reject__actions">
            <button type="submit" class="btn-reject">Reject booking</button>
            <a class="btn-ghost" href="#req-{{ $r->id }}" data-rb-reveal-close>Cancel</a>
          </div>
        </form>
      </article>
      @empty
        <p class="req-group__none" role="status">{{ $waiting->isEmpty() ? 'Nothing is waiting for approval.' : $group['empty'] }}@if ($waiting->isNotEmpty() && $otherGroup['items']->isNotEmpty()) @php $n = $otherGroup['items']->count(); $noun = ($otherGroup['key'] === 'room' ? 'room request' : 'equipment-only request').($n === 1 ? '' : 's'); @endphp<a href="{{ route('requests', $otherGroup['key'] === 'room' ? [] : ['tab' => $otherGroup['key']]) }}">{{ $n }} {{ $noun }} {{ $n === 1 ? 'is' : 'are' }} waiting.</a>@endif</p>
      @endforelse
    </section>
  </div>

  @if ($decided->isNotEmpty())
    <div class="rb-booking__panel">
    <h2>Recently decided</h2>
    <ul class="decided">
      @foreach ($decided as $r)
        <li class="decided-row">
          <span class="status-badge st-{{ strtolower($r->status) }}">{{ $r->statusLabel() }}</span>
          <div class="decided-main">
            <a class="decided-title" href="{{ route('bookings.show', [$r, 'from' => 'requests']) }}">{{ $r->purpose }}</a>
            <p class="decided-meta"><span>{{ $r->user->displayName() }}</span><span>{{ $r->start_time->format('M j, g:i A') }}</span><span>Decided by {{ $r->reviewer?->name }}</span></p>
            @if ($r->status === 'Rejected' && filled($r->status_reason))
              <p class="decided-why">“{{ $r->status_reason }}”</p>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
    </div>
  @endif
</section>
@endsection
