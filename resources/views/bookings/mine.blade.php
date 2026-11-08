@extends('layouts.app')
@use('App\Support\BookingRules')

@section('title', 'My bookings')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
@endpush

@section('content')
<section class="card mine-card rb-booking rb-ui">
  <div class="mine-head">
    <div>
      <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
      <h1>My bookings</h1>
      <p class="muted">You can cancel a booking until {{ BookingRules::duration($rules->cancelMinutes) }} before it starts.</p>
    </div>
    <a class="btn-book" href="{{ route('book') }}">+ New booking</a>
  </div>

  <div class="rb-booking__panel">
  <h2>Coming up <span class="count">{{ $upcoming->count() }}</span></h2>
  @if ($upcoming->isEmpty())
    <p class="empty">Nothing coming up. <a href="{{ route('book') }}">Book a room or equipment</a>.</p>
  @else
    <ul class="blist">
      @foreach ($upcoming as $b)
        @include('bookings._row', ['b' => $b, 'rules' => $rules])
      @endforeach
    </ul>
  @endif
  </div>

  <div class="rb-booking__panel" id="past">
  <h2>Past and closed <span class="count">{{ method_exists($past, 'total') ? $past->total() : $past->count() }}</span></h2>
  @if ($past->isEmpty())
    <p class="empty">None yet.</p>
  @else
    <ul class="blist past">
      @foreach ($past as $b)
        @include('bookings._row', ['b' => $b, 'rules' => $rules])
      @endforeach
    </ul>
    @if (method_exists($past, 'total'))
      <x-ui.pager :paginator="$past" noun="bookings" />
    @endif
  @endif
  </div>
</section>
@endsection
