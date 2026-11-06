{{-- Lab staff buttons for one booking: hand out, mark returned (with an optional note), no-show.
     Hand out and no-show show from the booking's own day (StaffRules::isFutureDay hides them before). Needs $r (a Reservation) and $staff (StaffRules). --}}
@php $returnBag = $errors->getBag('return'.$r->id); @endphp
<div class="staff-actions">
  @if ($r->status === 'Confirmed' && $staff->isFutureDay($r->start_time))
    <p class="hint">Hand out and no-show open on {{ $r->start_time->format('l, F j') }}.</p>
  @endif
  @if ($staff->canHandOut($r->status, $r->start_time))
    <form method="post" action="{{ route('bookings.hand-out', $r) }}">
      @csrf
      <button type="submit" class="btn-approve">Hand out key and items<span class="sr-only"> for booking #{{ $r->id }}</span></button>
    </form>
  @endif
  @if ($staff->canMarkNoShow($r->status, $r->start_time))
    {{-- A no-show can't be undone, so the browser asks first --}}
    <form method="post" action="{{ route('bookings.no-show', $r) }}" onsubmit="return confirm('Mark booking #{{ $r->id }} as a no-show? This frees the room and items and can’t be undone.')">
      @csrf
      <button type="submit" class="btn-noshow">No-show<span class="sr-only"> for booking #{{ $r->id }}</span></button>
    </form>
  @endif
  @if ($r->status === 'InUse')
    <details class="return" @if ($returnBag->any()) open @endif>
      <summary>Mark returned…</summary>
      <form method="post" action="{{ route('bookings.returned', $r) }}">
        @csrf
        <label for="note-{{ $r->id }}" class="field-label">Note <span class="optional">(optional: anything missing or damaged?)</span></label>
        <textarea id="note-{{ $r->id }}" name="note" rows="2" maxlength="255" placeholder="e.g. One HDMI cable missing">{{ $returnBag->any() ? old('note') : '' }}</textarea>
        @if ($returnBag->any())
          <p class="field-error">{{ $returnBag->first('note') }}</p>
        @endif
        <button type="submit" class="btn-approve">Mark returned</button>
      </form>
    </details>
  @endif
</div>
