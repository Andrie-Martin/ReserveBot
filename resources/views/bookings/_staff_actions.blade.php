{{-- Lab staff buttons for one booking: hand out, mark returned (with an optional note), no-show.
     They show at any time, on any day, so lab staff and admins can step in by hand. Needs $r (a Reservation) and $staff (StaffRules). --}}
@php $returnBag = $errors->getBag('return'.$r->id); @endphp
<div class="staff-actions">
  @if ($staff->canHandOut($r->status))
    <form method="post" action="{{ route('bookings.hand-out', $r) }}">
      @csrf
      <button type="submit" class="btn-approve">Hand out key and items<span class="sr-only"> for booking #{{ $r->id }}</span></button>
    </form>
  @endif
  @if ($staff->canMarkNoShow($r->status))
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
