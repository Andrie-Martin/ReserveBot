@extends('layouts.app')
@use('App\Support\TimeSlots')

@section('title', 'Edit '.$room->name)

@section('content')
<section class="card manage-card">
  <h1>Manage</h1>
  @include('manage._tabs')

  <p class="back"><a href="{{ route('manage.rooms') }}">‹ All rooms</a></p>
  <h2 class="edit-title">{{ $room->name }}</h2>
  @if ($sharedWith->isNotEmpty())
    <p class="notice">Shared with {{ $sharedWith->join(', ', ' and ') }}: changes here show for them too.</p>
  @endif

  <form method="post" action="{{ route('manage.rooms.update', $room) }}" class="mform">
    @csrf
    @method('PUT')
    @include('manage._errors', ['bag' => $errors->getBag('default')])
    <div class="mgrid">
      <div>
        <label for="name" class="field-label">Name</label>
        <input id="name" name="name" maxlength="30" required value="{{ old('name', $room->name) }}">
      </div>
      <div>
        <label for="building" class="field-label">Building <span class="optional">(optional)</span></label>
        <input id="building" name="building" maxlength="60" value="{{ old('building', $room->building) }}">
      </div>
      <div>
        <label for="capacity" class="field-label">Seats <span class="optional">(optional)</span></label>
        <input id="capacity" name="capacity" type="number" min="0" max="1000" value="{{ old('capacity', $room->capacity) }}">
      </div>
      <div>
        <label for="note" class="field-label">Note <span class="optional">(optional)</span></label>
        <input id="note" name="note" maxlength="200" value="{{ old('note', $room->fixed_equipment_note) }}">
      </div>
    </div>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('_token') ? old('is_active') : $room->is_active)> Can be booked <span class="optional">(untick to turn the room off)</span></label>
    <button type="submit" class="btn-small">Save</button>
  </form>

  <h2>Upcoming bookings in this room <span class="count">{{ $upcoming->count() }}</span></h2>
  @if (! $room->is_active && $upcoming->isNotEmpty())
    <p class="error">This room is off, but these bookings still use it. Open each one to move or cancel it.</p>
  @endif
  @if ($upcoming->isEmpty())
    <p class="empty">None.</p>
  @else
    <ul class="mlist">
      @foreach ($upcoming as $b)
        <li>
          <span class="mlist-when">{{ $b->start_time->format('D, M j') }} · {{ TimeSlots::span($b->start_time->hour * 60 + $b->start_time->minute, $b->end_time->isSameDay($b->start_time) ? $b->end_time->hour * 60 + $b->end_time->minute : 1440) }}</span>
          <span class="mlist-what">{{ $b->user->name }}@if ($b->user->section) ({{ $b->user->section->name }})@endif: {{ $b->purpose }}</span>
          <span class="status-badge st-{{ strtolower($b->status) }}">{{ $b->statusLabel() }}</span>
          <a href="{{ route('bookings.show', $b) }}#change">Open</a>
        </li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
