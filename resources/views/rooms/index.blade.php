@extends('layouts.app')
@use('App\Support\TimeSlots')
@use('App\Services\RoomAvailability')

@section('title', 'Free rooms')
@section('main-class', 'wide')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/availability.css') }}?v={{ filemtime(public_path('css/availability.css')) }}">
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
  @include('rooms._calendar')
  @include('rooms._day')
</div>
@endsection
