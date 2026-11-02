@extends('layouts.app')

@section('title', 'Manage rooms')

@section('content')
<section class="card manage-card">
  <h1>Manage</h1>
  @include('manage._tabs')

  <p class="muted">{{ $department->name }}’s rooms. Rooms are shared between departments, so a change shows for every department that uses the room. A room that’s off can’t be booked; bookings already made keep it.</p>

  <table class="mtable">
    <thead>
      <tr><th>Room</th><th class="hide-sm">Building</th><th>Seats</th><th class="hide-sm">Note</th><th>Status</th><th><span class="sr-only">Edit</span></th></tr>
    </thead>
    <tbody>
      @foreach ($rooms as $room)
        <tr @class(['off' => ! $room->is_active])>
          <td><strong>{{ $room->name }}</strong>@if ($room->departments_count > 1) <span class="tag-shared">shared</span>@endif</td>
          <td class="hide-sm">{{ $room->building ?? '—' }}</td>
          <td>{{ $room->capacity ?? 'Not recorded' }}</td>
          <td class="hide-sm note">{{ $room->fixed_equipment_note }}</td>
          <td><span @class(['status-badge', 'st-confirmed' => $room->is_active, 'st-cancelled' => ! $room->is_active])>{{ $room->is_active ? 'On' : 'Off' }}</span></td>
          <td><a href="{{ route('manage.rooms.edit', $room) }}">Edit<span class="sr-only"> {{ $room->name }}</span></a></td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <details class="add-box" @if ($errors->getBag('add')->any()) open @endif>
    <summary>+ Add a room</summary>
    <form method="post" action="{{ route('manage.rooms.store') }}" class="mform">
      @csrf
      @include('manage._errors', ['bag' => $errors->getBag('add')])
      <div class="mgrid">
        <div>
          <label for="add-name" class="field-label">Name</label>
          <input id="add-name" name="name" maxlength="30" required value="{{ old('name') }}" placeholder="e.g. CEA400">
        </div>
        <div>
          <label for="add-building" class="field-label">Building <span class="optional">(optional)</span></label>
          <input id="add-building" name="building" maxlength="60" value="{{ old('building') }}">
        </div>
        <div>
          <label for="add-capacity" class="field-label">Seats <span class="optional">(optional)</span></label>
          <input id="add-capacity" name="capacity" type="number" min="0" max="1000" value="{{ old('capacity') }}">
        </div>
        <div>
          <label for="add-note" class="field-label">Note <span class="optional">(optional)</span></label>
          <input id="add-note" name="note" maxlength="200" value="{{ old('note') }}" placeholder="e.g. Has a TV and aircon">
        </div>
      </div>
      <button type="submit" class="btn-small">Add room</button>
      <p class="hint">If another department already has a room with this name, it’s shared with you instead of copied.</p>
    </form>
  </details>
</section>
@endsection
