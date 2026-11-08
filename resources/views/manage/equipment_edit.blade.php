@extends('layouts.app')
@use('App\Support\TimeSlots')

@section('title', 'Edit '.$item->name)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Edit {{ $item->name }}</h1>
  @include('manage._tabs')

  <p class="back"><a href="{{ route('manage.equipment') }}">‹ All equipment</a></p>
  </header>

  <div class="rb-booking__panel">
  <p class="edit-title">{{ $item->name }}</p>

  <form method="post" action="{{ route('manage.equipment.update', $item) }}" class="mform">
    @csrf
    @method('PUT')
    @php
        $itemBag = $errors->getBag('default');
        $itemFocus = collect(['category_id', 'name', 'total_qty', 'max_per_request'])->first(fn ($field) => $itemBag->has($field));
        $itemInvalid = fn (string $field) => ($itemBag->has($field) ? 'aria-invalid="true" aria-describedby="item-'.$field.'-error"' : '').($itemFocus === $field ? ' autofocus' : '');
        $itemMsg = fn (string $field) => $itemBag->has($field) ? new \Illuminate\Support\HtmlString('<p class="field-error" id="item-'.$field.'-error">'.e($itemBag->first($field)).'</p>') : '';
      @endphp
      @if ($itemBag->any())<div id="item-errors">@include('manage._errors', ['bag' => $itemBag])</div>@endif
    <div class="mgrid">
      <div>
        <label for="category_id" class="field-label">Category</label>
          {!! $itemMsg('category_id') !!}
        <select id="category_id" name="category_id" required {!! $itemInvalid('category_id') !!}>
          @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((int) old('category_id', $item->category_id) === $category->id)>{{ $category->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="name" class="field-label">Name</label>
          {!! $itemMsg('name') !!}
        <input id="name" name="name" maxlength="60" required {!! $itemInvalid('name') !!} value="{{ old('name', $item->name) }}">
      </div>
      <div>
        <label for="total_qty" class="field-label">Stock</label>
          {!! $itemMsg('total_qty') !!}
        <input id="total_qty" name="total_qty" type="number" min="0" max="9999" required {!! $itemInvalid('total_qty') !!} value="{{ old('total_qty', $item->total_qty) }}">
      </div>
      <div>
        <label for="max_per_request" class="field-label">Most per booking</label>
          {!! $itemMsg('max_per_request') !!}
        <input id="max_per_request" name="max_per_request" type="number" min="1" max="99" required {!! $itemInvalid('max_per_request') !!} value="{{ old('max_per_request', $item->max_per_request) }}">
      </div>
    </div>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($errors->getBag('default')->any() ? old('is_active') : $item->is_active)> Can be booked <span class="optional">(untick to turn the item off)</span></label>
    <button type="submit" class="btn-small">Save</button>
  </form>
  </div>

  <div class="rb-booking__panel">
  <h2>Upcoming bookings with this item <span class="count">{{ $upcoming->count() }}</span></h2>
  @if ($over)
    <p class="error">{{ count($over) }} {{ count($over) === 1 ? 'booking asks' : 'bookings ask' }} for more {{ $item->name }} than you have ({{ $item->total_qty }}) at the same time. Open {{ count($over) === 1 ? 'it' : 'each one' }} to move or cancel it, or raise the stock.</p>
  @endif
  @if ($upcoming->isEmpty())
    <p class="empty">None.</p>
  @else
    <ul class="mlist">
      @foreach ($upcoming as $b)
        <li @class(['over' => in_array($b->id, $over, true)])>
          <span class="mlist-when">{{ $b->start_time->format('D, M j') }} · {{ TimeSlots::span($b->start_time->hour * 60 + $b->start_time->minute, $b->end_time->isSameDay($b->start_time) ? $b->end_time->hour * 60 + $b->end_time->minute : 1440) }}</span>
          <span class="mlist-what">×{{ $b->asked }} · {{ $b->room?->name ?? 'No room' }} · {{ $b->user->name }}@if ($b->user->section) ({{ $b->user->section->name }})@endif</span>
          @if (in_array($b->id, $over, true))<span class="tag-warn">Too many</span>@endif
          <span class="status-badge st-{{ strtolower($b->status) }}">{{ $b->statusLabel() }}</span>
          <a href="{{ route('bookings.show', $b) }}#change">Open</a>
        </li>
      @endforeach
    </ul>
  @endif
  </div>
</section>
@endsection
