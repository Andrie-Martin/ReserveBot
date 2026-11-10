{{-- One booking on the Today page. Needs $r (a Reservation) and $staff (StaffRules). --}}
@php
  $start = $r->start_time;
  $end = $r->end_time;
  $span = \App\Support\TimeSlots::span($start->hour * 60 + $start->minute, $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440);
  $overdue = $staff->isOverdue($r->status, $end);
  $late = $staff->isLate($r->status, $start);
  $notes = ['Rejected' => 'Why it was rejected', 'Cancelled' => 'Why it was cancelled', 'Completed' => 'Return note'];
@endphp
<article @class(['tcard', 'st-'.strtolower($r->status), 'alert' => $overdue || $late]) id="booking-{{ $r->id }}">
  <div class="tcard-time">
    <strong>{{ $span }}</strong>
    @if ($overdue)
      <span class="tag-warn">Overdue</span>
    @elseif ($late)
      <span class="tag-warn">Late</span>
    @endif
  </div>
  <div class="tcard-main">
    <p class="tcard-title">{{ $r->room?->name ?? 'Equipment only' }} · {{ $r->user->displayName() }}@if ($r->representativeName()) <span class="muted">(Rep: {{ $r->representativeName() }})</span>@endif</p>
    {{-- Who to expect: for equipment, the borrower typed on the booking (check their ID); otherwise only the room's key --}}
    <p class="tcard-who"><x-avatar :user="$r->user" size="md" /> <span>@if (! $r->hasEquipment())Room key only @elseif ($r->hasBorrower())Borrower: <strong>{{ $r->borrower_name }}</strong> · ID <span class="who-id-inline">{{ $r->borrower_id_number }}</span>@else Borrower not recorded @endif</span></p>
    <p class="tcard-reason">{{ $r->purpose }}@if ($r->classSchedule) <span class="muted">· {{ $r->classSchedule->course?->code ?? 'Class' }} class</span>@endif</p>
    @if ($r->hasEquipment())
      <p class="tcard-items">{{ $r->itemsText() }}</p>
    @endif
    @if (filled($r->status_reason) && isset($notes[$r->status]))
      <p class="tcard-note">{{ $notes[$r->status] }}: “{{ $r->status_reason }}”</p>
    @endif
  </div>
  <div class="tcard-side">
    <span class="status-badge st-{{ strtolower($r->status) }}">{{ $r->statusLabel() }}</span>
    @include('bookings._staff_actions', ['r' => $r, 'staff' => $staff])
    {{-- Navigation only (an anchor styled as the shared outlined secondary button); nothing is approved or rejected from here. --}}
    @if ($staff->canChange($r->status, $end))
      <x-ui.link-button variant="secondary" size="compact" class="tcard-link" :href="route('bookings.show', $r).'#change'">Cancel or move<span class="sr-only"> booking #{{ $r->id }}</span></x-ui.link-button>
    @elseif ($r->status === 'Pending')
      <x-ui.link-button variant="secondary" size="compact" class="tcard-link" :href="route('bookings.show', $r)">Review request<span class="sr-only"> for booking #{{ $r->id }}</span></x-ui.link-button>
    @else
      <x-ui.link-button variant="secondary" size="compact" class="tcard-link" :href="route('bookings.show', $r)">Details<span class="sr-only"> for booking #{{ $r->id }}</span></x-ui.link-button>
    @endif
  </div>
</article>
