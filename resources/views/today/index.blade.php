@extends('layouts.app')

@section('title', "Today's schedule")

@section('content')
<section class="card today-card">
  <div class="today-head">
    <div>
      <p class="eyebrow">{{ $date->isToday() ? 'Today' : ($date->isPast() ? 'Earlier day' : 'Coming day') }}</p>
      <h1>{{ $date->format('l, F j') }}</h1>
      <p class="muted">{{ $department->name }} · Hand out keys and items, take them back, and mark no-shows.</p>
    </div>
    <div class="daynav">
      @if ($prevUrl)
        <a class="btn-ghost" href="{{ $prevUrl }}">‹ Previous day</a>
      @endif
      @unless ($date->isToday())
        <a class="btn-ghost" href="{{ route('today') }}">Today</a>
      @endunless
      @if ($nextUrl)
        <a class="btn-ghost" href="{{ $nextUrl }}">Next day ›</a>
      @endif
    </div>
  </div>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  <ul class="today-stats" aria-label="Summary">
    <li><strong>{{ $inUse->count() }}</strong> in use now</li>
    <li><strong>{{ $toHandOut->count() }}</strong> to hand out</li>
    <li><strong>{{ $waiting->count() }}</strong> waiting for approval</li>
    <li><strong>{{ $done->count() }}</strong> done</li>
  </ul>

  <h2>In use now <span class="count">{{ $inUse->count() }}</span></h2>
  @forelse ($inUse as $r)
    @include('today._card', ['r' => $r, 'staff' => $staff])
  @empty
    <p class="empty">Nothing is out right now.</p>
  @endforelse

  <h2>To hand out <span class="count">{{ $toHandOut->count() }}</span></h2>
  @forelse ($toHandOut as $r)
    @include('today._card', ['r' => $r, 'staff' => $staff])
  @empty
    <p class="empty">No approved bookings left to hand out{{ $date->isToday() ? ' today' : '' }}.</p>
  @endforelse

  @if ($waiting->isNotEmpty())
    <h2>Waiting for approval <span class="count">{{ $waiting->count() }}</span></h2>
    @foreach ($waiting as $r)
      @include('today._card', ['r' => $r, 'staff' => $staff])
    @endforeach
  @endif

  @if ($done->isNotEmpty())
    <details class="done-list">
      <summary>Done <span class="count">{{ $done->count() }}</span> <span class="muted">completed, no-shows, cancelled and rejected</span></summary>
      @foreach ($done as $r)
        @include('today._card', ['r' => $r, 'staff' => $staff])
      @endforeach
    </details>
  @endif

  <p class="hint">The buttons work at any time. A booking shows <strong>Late</strong> when it isn’t picked up {{ $staff->graceMinutes }} minutes after it starts, and <strong>Overdue</strong> when items are still out after it ends.@if ($date->gte(today())) For each room’s full day, see <a href="{{ route('rooms', ['date' => $date->toDateString()]) }}#day">Free rooms</a>.@endif</p>
</section>
@endsection
