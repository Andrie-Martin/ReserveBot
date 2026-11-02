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
    <p class="tcard-title">{{ $r->room?->name ?? 'Equipment only' }} · {{ $r->user->name }}@if ($r->user->section) <span class="muted">({{ $r->user->section->name }})</span>@endif</p>
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
    @if ($staff->canChange($r->status, $end))
      <a class="req-link" href="{{ route('bookings.show', $r) }}#change">Cancel or move…</a>
    @elseif ($r->status === 'Pending')
      <a class="req-link" href="{{ route('bookings.show', $r) }}">Approve or reject…</a>
    @else
      <a class="req-link" href="{{ route('bookings.show', $r) }}">Details</a>
    @endif
  </div>
</article>
