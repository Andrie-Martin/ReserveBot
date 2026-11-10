@extends('layouts.app')
@use('App\Support\TimeSlots')

@section('title', 'Edit '.$room->name)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Edit {{ $room->name }}</h1>
  @include('manage._tabs')

  <p class="back"><a href="{{ route('manage.rooms') }}">‹ All rooms</a></p>
  </header>

  <div class="rb-booking__panel">
  <p class="edit-title">{{ $room->name }}</p>
  @if ($sharedWith->isNotEmpty())
    <p class="notice">Shared with {{ $sharedWith->join(', ', ' and ') }}: changes here show for them too.</p>
  @endif

  <form method="post" action="{{ route('manage.rooms.update', $room) }}" class="mform">
    @csrf
    @method('PUT')
    @php
        $roomBag = $errors->getBag('default');
        $roomFocus = collect(['name', 'building', 'capacity', 'note'])->first(fn ($field) => $roomBag->has($field));
        $roomInvalid = fn (string $field) => ($roomBag->has($field) ? 'aria-invalid="true" aria-describedby="room-'.$field.'-error"' : '').($roomFocus === $field ? ' autofocus' : '');
        $roomMsg = fn (string $field) => $roomBag->has($field) ? new \Illuminate\Support\HtmlString('<p class="field-error" id="room-'.$field.'-error">'.e($roomBag->first($field)).'</p>') : '';
      @endphp
      @if ($roomBag->any())<div id="room-errors">@include('manage._errors', ['bag' => $roomBag])</div>@endif
    <div class="mgrid">
      <div>
        <label for="name" class="field-label">Name</label>
          {!! $roomMsg('name') !!}
        <input id="name" name="name" {!! $roomInvalid('name') !!} maxlength="30" required value="{{ old('name', $room->name) }}">
      </div>
      <div>
        <label for="building" class="field-label">Building <span class="optional">(optional)</span></label>
          {!! $roomMsg('building') !!}
        <input id="building" name="building" {!! $roomInvalid('building') !!} maxlength="60" value="{{ old('building', $room->building) }}">
      </div>
      <div>
        <label for="capacity" class="field-label">Seats <span class="optional">(optional)</span></label>
          {!! $roomMsg('capacity') !!}
        <input id="capacity" name="capacity" {!! $roomInvalid('capacity') !!} type="number" min="0" max="1000" value="{{ old('capacity', $room->capacity) }}">
      </div>
      <div>
        <label for="note" class="field-label">Note <span class="optional">(optional)</span></label>
          {!! $roomMsg('note') !!}
        <input id="note" name="note" {!! $roomInvalid('note') !!} maxlength="200" value="{{ old('note', $room->fixed_equipment_note) }}">
      </div>
    </div>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($errors->getBag('default')->any() ? old('is_active') : $room->is_active)> Can be booked <span class="optional">(untick to turn the room off)</span></label>
    <button type="submit" class="btn-small">Save</button>
  </form>
  </div>

  <div class="rb-booking__panel">
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
  </div>

  @include('manage._delete', ['what' => 'room', 'name' => $room->name, 'action' => route('manage.rooms.destroy', $room), 'blockers' => $deleteBlockers])
</section>
@endsection
