@extends('layouts.app')
@use('App\Support\BookingRules')

@section('title', 'My bookings')

@section('content')
<section class="card mine-card">
  <div class="mine-head">
    <div>
      <h1>My bookings</h1>
      <p class="muted">You can cancel a booking until {{ BookingRules::duration($rules->cancelMinutes) }} before it starts.</p>
    </div>
    <a class="btn-book" href="{{ route('book') }}">+ New booking</a>
  </div>

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

  <h2>Past and closed <span class="count">{{ $past->count() }}</span></h2>
  @if ($past->isEmpty())
    <p class="empty">None yet.</p>
  @else
    <ul class="blist past">
      @foreach ($past as $b)
        @include('bookings._row', ['b' => $b, 'rules' => $rules])
      @endforeach
    </ul>
    <p class="hint">Shows your last 30.</p>
  @endif
</section>
@endsection
