@extends('layouts.app')
@use('App\Support\TimeSlots')
@use('App\Services\RoomAvailability')

@section('title', 'Free rooms')
@section('main-class', 'wide')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/availability.css') }}?v={{ filemtime(public_path('css/availability.css')) }}">
<script src="{{ asset('js/availability.js') }}?v={{ filemtime(public_path('js/availability.js')) }}" defer></script>
@endpush

@section('content')
@php
  $clock = '<svg class="rb-availability__ico" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.25"/><path d="M8 4.75V8l2.25 1.5"/></svg>';
  $pin = '<svg class="rb-availability__ico" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 14.5s4.75-4.2 4.75-8a4.75 4.75 0 0 0-9.5 0c0 3.8 4.75 8 4.75 8z"/><circle cx="8" cy="6.5" r="1.6"/></svg>';
  $box = '<svg class="rb-availability__ico" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 5 8 2.25 13.5 5v6L8 13.75 2.5 11z"/><path d="M2.5 5 8 7.75 13.5 5M8 7.75v6"/></svg>';
  // Words for a status tag in a room's list: a class whose room is booked, or a booking
  $statusWords = fn ($item) => ($item['kind'] === 'class' ? 'room booked, ' : '').RoomAvailability::STATUS_WORDS[$item['status']];
@endphp

<div class="rb-availability rb-ui">
  <div class="rb-availability__cal-intro">
    <p class="rb-availability__kicker">Workspace</p>
    <h1>Free rooms</h1>
    <p class="rb-availability__muted">{{ $department->name }} · Pick a day, up to {{ $lastDay->format('F j') }}, to see which rooms are free.</p>
  </div>

  @include('rooms._day')
  @if ($isOpen)
    @include('rooms._results')
  @endif
  <section class="rb-availability__secondary" id="calendar-and-agenda" aria-label="Calendar and selected-day agenda">
    @include('rooms._calendar')
    @include('rooms._calendar-details')
    @if ($isOpen)
      <section class="rb-card rb-availability__agenda-card" aria-label="Selected-day agenda">
        @include('rooms._agenda')
      </section>
    @endif
  </section>
</div>
@endsection
