@extends('layouts.app')

@section('title', 'Manage rooms')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Manage rooms</h1>
  @include('manage._tabs')

  <p class="muted">{{ $department->name }}’s rooms. Rooms are shared between departments, so a change shows for every department that uses the room. A room that’s off can’t be booked; bookings already made keep it.</p>
  </header>

  <div class="add-row rb-booking__panel">
  <details class="add-box" @if ($errors->getBag('add')->any()) open @endif>
    <summary>+ Add a room</summary>
    <form method="post" action="{{ route('manage.rooms.store') }}" class="mform">
      @csrf
      @php
        $addBag = $errors->getBag('add');
        $addFocus = collect(['name', 'building', 'capacity', 'note'])->first(fn ($field) => $addBag->has($field));
        $addInvalid = fn (string $field) => ($addBag->has($field) ? 'aria-invalid="true" aria-describedby="add-'.$field.'-error"' : '').($addFocus === $field ? ' autofocus' : '');
        $addMsg = fn (string $field) => $addBag->has($field) ? new \Illuminate\Support\HtmlString('<p class="field-error" id="add-'.$field.'-error">'.e($addBag->first($field)).'</p>') : '';
      @endphp
      @if ($addBag->any())<div id="add-errors">@include('manage._errors', ['bag' => $addBag])</div>@endif
      <div class="mgrid">
        <div>
          <label for="add-name" class="field-label">Name</label>
          {!! $addMsg('name') !!}
          <input id="add-name" name="name" {!! $addInvalid('name') !!} maxlength="30" required value="{{ old('name') }}" placeholder="e.g. CEA400">
        </div>
        <div>
          <label for="add-building" class="field-label">Building <span class="optional">(optional)</span></label>
          {!! $addMsg('building') !!}
          <input id="add-building" name="building" {!! $addInvalid('building') !!} maxlength="60" value="{{ old('building') }}">
        </div>
        <div>
          <label for="add-capacity" class="field-label">Seats <span class="optional">(optional)</span></label>
          {!! $addMsg('capacity') !!}
          <input id="add-capacity" name="capacity" {!! $addInvalid('capacity') !!} type="number" min="0" max="1000" value="{{ old('capacity') }}">
        </div>
        <div>
          <label for="add-note" class="field-label">Note <span class="optional">(optional)</span></label>
          {!! $addMsg('note') !!}
          <input id="add-note" name="note" {!! $addInvalid('note') !!} maxlength="200" value="{{ old('note') }}" placeholder="e.g. Has a TV and aircon">
        </div>
      </div>
      <button type="submit" class="btn-small">Add room</button>
      <p class="hint">If another department already has a room with this name, it’s shared with you instead of copied.</p>
    </form>
  </details>
  </div>

  <div class="rb-booking__panel">
  @if ($rooms->isEmpty())
    <p class="empty">No rooms yet. Use “+ Add a room” above to add the first one; it can be booked as soon as it’s added.</p>
  @else
  <table class="mtable rooms-table">
    <colgroup><col class="c-room"><col class="c-building"><col class="c-seats"><col class="c-note"><col class="c-status"><col class="c-edit"></colgroup>
    <thead>
      <tr><th>Room</th><th class="hide-sm">Building</th><th>Seats</th><th class="hide-sm">Note</th><th>Status</th><th><span class="sr-only">Edit</span></th></tr>
    </thead>
    <tbody>
      @foreach ($rooms as $room)
        <tr @class(['off' => ! $room->is_active])>
          <td><strong>{{ $room->name }}</strong>@if ($room->departments_count > 1) <span class="tag-shared">shared</span>@endif</td>
          <td class="hide-sm">{{ $room->building ?? '—' }}</td>
          <td>{{ $room->capacity ?? 'Not specified' }}</td>
          <td class="hide-sm note">{{ $room->fixed_equipment_note }}</td>
          <td><span @class(['status-badge', 'st-confirmed' => $room->is_active, 'st-cancelled' => ! $room->is_active])>{{ $room->is_active ? 'On' : 'Off' }}</span></td>
          <td class="room-edit"><a class="row-edit" href="{{ route('manage.rooms.edit', $room) }}">Edit<span class="sr-only"> {{ $room->name }}</span></a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
  @endif
  </div>

</section>
@endsection
