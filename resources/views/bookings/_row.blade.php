{{-- One booking in a list (My bookings). Needs $b (a Reservation) and $rules (BookingRules). --}}
@php
  $start = $b->start_time;
  $end = $b->end_time;
  $span = \App\Support\TimeSlots::span($start->hour * 60 + $start->minute, $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440);
  $canCancel = in_array($b->status, \App\Models\Reservation::CANCELLABLE_STATUSES, true) && $rules->canCancel($start);
@endphp
<li class="brow">
  <div class="brow-when">
    <strong>{{ $start->format('D, M j') }}</strong>
    <span>{{ $span }}</span>
  </div>
  <div class="brow-what">
    <p class="brow-title"><a href="{{ route('bookings.show', $b) }}">{{ $b->purpose }}</a></p>
    <p class="brow-meta">{{ $b->needLabel() }} · {{ $b->room?->name ?? 'No room' }}@if ($b->classSchedule) · {{ $b->classSchedule->course?->code ?? 'Class' }} class @endif</p>
    @if ($b->hasEquipment())
      <p class="brow-items">{{ $b->itemsText() }}</p>
    @endif
    @if (filled($b->status_reason) && in_array($b->status, ['Rejected', 'Cancelled', 'Completed'], true))
      <p @class(['brow-reason', 'note' => $b->status === 'Completed'])>{{ ['Rejected' => 'Rejected', 'Cancelled' => 'Cancelled', 'Completed' => 'Return note'][$b->status] }}: “{{ $b->status_reason }}”</p>
    @endif
  </div>
  <div class="brow-side">
    <span class="status-badge st-{{ strtolower($b->status) }}">{{ $b->statusLabel() }}</span>
    @if ($canCancel)
      <a class="brow-cancel" href="{{ route('bookings.show', $b) }}#cancel">Cancel…</a>
    @endif
  </div>
</li>
