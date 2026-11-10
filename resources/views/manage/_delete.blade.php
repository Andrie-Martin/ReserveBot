{{-- The delete section of a room / equipment edit page. Needs $what ('room'|'item'), $name, $action (the DELETE route),
     $blockers (reasons it can't be deleted, empty = allowed). Deleting is a real DELETE form with CSRF: a link or a GET never deletes.
     Without script it still needs the tick box, and the server refuses a request without it. --}}
@php $deleteBag = $errors->getBag('delete'); @endphp
<div class="rb-booking__panel rb-delete" id="delete">
  <h2 id="delete-title" class="rb-delete__title">Delete {{ $name }}</h2>
  @if ($deleteBag->any())
    <div id="delete-errors">@include('manage._errors', ['bag' => $deleteBag])</div>
  @endif
  @if ($blockers === [])
    <p>This removes the {{ $what }} <strong>{{ $name }}</strong> for good. It has no bookings{{ $what === 'room' ? ' or classes' : '' }} on record, so no history is lost. This can’t be undone.</p>
    <p class="hint">Not sure? Turn it off instead: untick “Can be booked” above and save. It stays in the list and can be turned on again.</p>
    <form method="post" action="{{ $action }}" class="rb-delete__form" aria-labelledby="delete-title">
      @csrf
      @method('DELETE')
      <label class="check"><input type="checkbox" name="confirm" value="1" required> Yes, delete {{ $name }}</label>
      <x-ui.button type="submit" variant="danger">Delete {{ $name }}</x-ui.button>
    </form>
  @else
    <p><strong>{{ $name }}</strong> can’t be deleted:</p>
    <ul class="rb-delete__reasons">
      @foreach ($blockers as $reason)
        <li>{{ $reason }}</li>
      @endforeach
    </ul>
    <p class="hint">{{ \App\Support\ResourceDeletion::TURN_OFF_ADVICE }}</p>
  @endif
</div>
