@extends('layouts.app')
@use('App\Support\TimeSlots')
@use('App\Support\BookingRules')
@use('App\Http\Controllers\BookingController')

@section('title', 'Book')

@section('content')
<section class="card book-card">
  <h1>Book a room or equipment</h1>
  <p class="muted">{{ $user->department->name }}@if ($user->section) · {{ $user->section->name }}@endif</p>

  <ol class="steps">
    <li @class(['on' => ! $when, 'done' => (bool) $when])><span>1</span>When</li>
    <li @class(['on' => (bool) $when])><span>2</span>What you need</li>
  </ol>

  @if (! $when)
    {{-- ============ Step 1: when ============ --}}
    @if ($whenError)
      <p class="error" role="alert">{{ $whenError }}</p>
    @endif

    <form method="get" action="{{ route('book') }}" class="book-form step1">
      @if (! empty($picked['room']))
        <input type="hidden" name="room" value="{{ $picked['room'] }}">
      @endif

      <fieldset class="choices">
        <legend>What is it for?</legend>
        <div class="choice-grid">
        @if ($canBookClass)
          <label class="choice">
            <input type="radio" name="for" value="class" @checked($for === 'class')>
            <span><strong>One of my classes</strong>Your block’s class, at its usual time and room</span>
          </label>
        @endif
        <label class="choice">
          <input type="radio" name="for" value="other" @checked($for === 'other')>
          <span><strong>Something else</strong>A make-up class, meeting or event, at a time you pick</span>
        </label>
        </div>
      </fieldset>

      @if ($canBookClass)
        <div class="when-class">
          <label for="session" class="field-label">Which class session?</label>
          @if ($sessions)
            <select id="session" name="session">
              <option value="">Pick a class</option>
              @foreach (collect($sessions)->groupBy(fn ($s) => $s['day']->toDateString()) as $daySessions)
                <optgroup label="{{ $daySessions[0]['day']->format('l, F j') }}">
                  @foreach ($daySessions as $s)
                    <option value="{{ $s['key'] }}" @selected(($picked['session'] ?? null) === $s['key'])>{{ $s['class']->course?->code ?? 'Class' }} · {{ TimeSlots::span($s['from'], $s['to']) }} · {{ $s['class']->room->name }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
            <p class="hint">Sessions you already booked, or that start in less than {{ BookingRules::duration($rules->noticeMinutes) }}, aren’t listed.</p>
          @else
            <p class="empty">You have no class sessions left to book between now and {{ $rules->lastDay()->format('F j') }}.</p>
          @endif
        </div>
      @endif

      <div class="when-other">
        <div class="field-row">
          <div>
            <label for="date" class="field-label">Day</label>
            <select id="date" name="date">
              @foreach ($days as $day)
                <option value="{{ $day->toDateString() }}" @selected(($picked['date'] ?? null) === $day->toDateString())>{{ $day->format('D, M j') }}{{ $day->isToday() ? ' (today)' : '' }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="from" class="field-label">From</label>
            <select id="from" name="from">
              <option value="">Start</option>
              @foreach ($rules->starts() as $t)
                <option value="{{ TimeSlots::hhmm($t) }}" @selected(($picked['from'] ?? null) === TimeSlots::hhmm($t))>{{ TimeSlots::clock($t) }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="to" class="field-label">To</label>
            <select id="to" name="to">
              <option value="">End</option>
              @foreach ($rules->ends() as $t)
                <option value="{{ TimeSlots::hhmm($t) }}" @selected(($picked['to'] ?? null) === TimeSlots::hhmm($t))>{{ TimeSlots::clock($t) }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <p class="hint">{{ BookingRules::duration($rules->minMinutes) }} to {{ BookingRules::duration($rules->maxMinutes) }} long, starting at least {{ BookingRules::duration($rules->noticeMinutes) }} from now. Not sure which rooms are free? <a href="{{ route('rooms') }}">Check the Free rooms page</a>.</p>
      </div>

      <button type="submit" class="btn-next">Next: what you need →</button>
    </form>
  @else
    {{-- ============ Step 2: what you need ============ --}}
    @php
      $session = $when['session'];
      $need = old('need', request('need', $needDefault));
      $pickedRoom = (int) old('room', $preRoom ?? 0);
      $maxPerItem = $categories->flatMap->equipment->max('max_per_request');
    @endphp

    <div class="when-summary">
      <div>
        <p class="eyebrow">When</p>
        <p class="when-main"><span>{{ $when['day']->format('l, F j') }}</span><span class="sep"> · </span><span>{{ TimeSlots::span($when['from'], $when['to']) }}</span></p>
        @if ($session)
          <p class="when-sub">{{ trim(($session['class']->course?->code ?? 'Class').' '.($session['class']->course?->title ?? '')) }} · {{ $user->section?->name }}</p>
        @else
          <p class="when-sub">Not a regular class</p>
        @endif
      </div>
      <a class="btn-ghost" href="{{ $changeUrl }}">Change</a>
    </div>

    @if ($errors->any())
      <div class="error" role="alert">
        <strong>Please check this:</strong>
        <ul>
          @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="post" action="{{ route('book.store') }}" class="book-form step2">
      @csrf
      @foreach ($when['params'] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
      @endforeach

      <fieldset class="choices need">
        <legend>What do you need?</legend>
        <div class="choice-grid">
        @foreach (BookingController::NEEDS as $value => $label)
          <label class="choice">
            <input type="radio" name="need" value="{{ $value }}" @checked($need === $value)>
            <span><strong>{{ $label }}</strong>{{ ['both' => 'The room, plus items like a projector', 'room' => 'Just the room (and its key)', 'equipment' => 'Borrow items; no room is held'][$value] }}</span>
          </label>
        @endforeach
        </div>
      </fieldset>

      {{-- Room: hidden by the stylesheet when "Equipment only" is picked --}}
      <section class="part part-room" aria-labelledby="room-title">
        <h2 id="room-title">Room</h2>
        @if ($session)
          <div @class(['class-room', 'taken' => $classRoomTaken])>
            <strong>{{ $classRoom->name }}</strong> · your class’s usual room
            @if ($classRoomTaken)
              <p class="error">{{ $classRoom->name }} is already booked during your class. You can still borrow equipment only.</p>
            @endif
          </div>
        @else
          @php
            $freeRooms = collect($rooms)->reject(fn ($row) => $row['clash']);
            $busyRooms = collect($rooms)->filter(fn ($row) => $row['clash']);
          @endphp
          @if ($freeRooms->isEmpty())
            <p class="error">No room is free for that whole time. <a href="{{ $changeUrl }}">Pick another time</a>, or choose Equipment only.</p>
          @else
            <p class="hint">These rooms are free for the whole time.</p>
            <div class="room-pick">
              @foreach ($freeRooms as $row)
                @php $room = $row['room']; @endphp
                <label class="room-opt">
                  <input type="radio" name="room" value="{{ $room->id }}" @checked($pickedRoom === $room->id)>
                  <span class="room-opt-name"><strong>{{ $room->name }}</strong>{{ $room->capacity ? $room->capacity.' seats' : '' }}</span>
                  <span class="room-opt-state free">Free</span>
                </label>
              @endforeach
            </div>
          @endif
          @if ($busyRooms->isNotEmpty())
            <details class="busy-rooms">
              <summary>{{ $busyRooms->count() }} {{ $busyRooms->count() === 1 ? 'room is' : 'rooms are' }} busy then (rooms are shared, so other departments count too)</summary>
              <ul>
                @foreach ($busyRooms as $row)
                  <li><strong>{{ $row['room']->name }}</strong> {{ $row['clash']['label'] }} ({{ TimeSlots::span($row['clash']['start'], $row['clash']['end']) }})</li>
                @endforeach
              </ul>
            </details>
          @endif
        @endif
      </section>

      {{-- Equipment: hidden by the stylesheet when "Room only" is picked --}}
      <section class="part part-equipment" aria-labelledby="eq-title">
        <h2 id="eq-title">Equipment</h2>
        <p class="hint">The number next to each item is how many are free at that time. You can ask for up to {{ $maxPerItem }} of each.</p>
        @foreach ($categories as $category)
          @php $pickedHere = $category->equipment->filter(fn ($item) => (int) old('qty.'.$item->id, 0) > 0)->count(); @endphp
          <details class="eq-cat" @if ($pickedHere) open @endif>
            <summary>{{ $category->name }} <span class="eq-count">{{ $category->equipment->count() }} items</span>@if ($pickedHere)<span class="eq-picked">{{ $pickedHere }} picked</span>@endif</summary>
            <div class="eq-grid">
              @foreach ($category->equipment as $item)
                <div @class(['eq-item', 'none-free' => $item->free === 0])>
                  <label for="qty-{{ $item->id }}">{{ $item->name }}</label>
                  <span class="eq-free">{{ $item->free > 0 ? $item->free.' free' : 'None free' }}</span>
                  <select id="qty-{{ $item->id }}" name="qty[{{ $item->id }}]" @disabled($item->free === 0)>
                    @for ($n = 0; $n <= min($item->max_per_request, $item->free); $n++)
                      <option value="{{ $n }}" @selected((int) old('qty.'.$item->id, 0) === $n)>{{ $n }}</option>
                    @endfor
                  </select>
                </div>
              @endforeach
            </div>
          </details>
        @endforeach
        <label for="other_items" class="field-label">Anything not on the list? <span class="optional">(optional)</span></label>
        <input id="other_items" name="other_items" maxlength="200" value="{{ old('other_items') }}" placeholder="e.g. Bluetooth speaker x1">
      </section>

      <section class="part" aria-labelledby="purpose-label">
        <label for="purpose" id="purpose-label" class="field-label">Reason</label>
        <textarea id="purpose" name="purpose" rows="2" maxlength="150" required placeholder="e.g. Lecture with slides, make-up class, lab activity, org meeting">{{ old('purpose') }}</textarea>
        <p class="hint">Up to 150 characters. Lab staff and admins see this when they review your booking.</p>
      </section>

      @if ($rules->needsApproval)
        <p class="notice">Your booking will wait for approval from lab staff or an admin. You’ll see its status on the Free rooms page.</p>
      @endif
      <button type="submit" class="btn-next">Send booking</button>
    </form>
  @endif
</section>
@endsection
