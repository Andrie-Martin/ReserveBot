@extends('layouts.app')

@section('title', 'Manage equipment')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Manage equipment</h1>
  @include('manage._tabs')

  <p class="muted">Stock is how many the department has. “Most per booking” is the most one booking can ask for. An item that’s off can’t be booked; bookings already made keep it.</p>
  </header>

  @foreach ($categories as $category)
    @php $renameBag = $errors->getBag('category'.$category->id); @endphp
    <section class="mcat rb-booking__panel">
      <div class="mcat-head">
        <h2>{{ $category->name }} <span class="count">{{ $category->equipment->count() }}</span></h2>
        <details class="rename" @if ($renameBag->any()) open @endif>
          <summary>Rename</summary>
          @if ($renameBag->any())
            <p class="field-error" id="cat-{{ $category->id }}-error">{{ $renameBag->first('name') }}</p>
          @endif
          <form method="post" action="{{ route('manage.categories.update', $category) }}" class="inline-form">
            @csrf
            @method('PUT')
            <label for="cat-{{ $category->id }}" class="sr-only">New name for {{ $category->name }}</label>
            <input id="cat-{{ $category->id }}" name="name" maxlength="60" required @if ($renameBag->any()) autofocus aria-invalid="true" aria-describedby="cat-{{ $category->id }}-error" @endif value="{{ $renameBag->any() ? old('name') : $category->name }}">
            <button type="submit" class="btn-small">Save</button>
          </form>
        </details>
      </div>
      @if ($category->equipment->isEmpty())
        <p class="empty">No items yet.</p>
      @else
        <table class="mtable">
          <thead><tr><th>Item</th><th>Stock</th><th>Most per booking</th><th>Status</th><th><span class="sr-only">Edit</span></th></tr></thead>
          <tbody>
            @foreach ($category->equipment as $item)
              <tr @class(['off' => ! $item->is_active])>
                <td>{{ $item->name }}</td>
                <td>{{ $item->total_qty }}</td>
                <td>{{ $item->max_per_request }}</td>
                <td><span @class(['status-badge', 'st-confirmed' => $item->is_active, 'st-cancelled' => ! $item->is_active])>{{ $item->is_active ? 'On' : 'Off' }}</span></td>
                <td><a href="{{ route('manage.equipment.edit', $item) }}">Edit<span class="sr-only"> {{ $item->name }}</span></a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </section>
  @endforeach

  <div class="add-row rb-booking__panel">
    <details class="add-box" @if ($errors->getBag('item')->any()) open @endif>
      <summary>+ Add an item</summary>
      <form method="post" action="{{ route('manage.equipment.store') }}" class="mform">
        @csrf
        @php $itemOld = $errors->getBag('item')->any(); @endphp
        @php
        $itemBag = $errors->getBag('item');
        $itemFocus = collect(['category_id', 'name', 'total_qty', 'max_per_request'])->first(fn ($field) => $itemBag->has($field));
        $itemInvalid = fn (string $field) => ($itemBag->has($field) ? 'aria-invalid="true" aria-describedby="item-'.$field.'-error"' : '').($itemFocus === $field ? ' autofocus' : '');
        $itemMsg = fn (string $field) => $itemBag->has($field) ? new \Illuminate\Support\HtmlString('<p class="field-error" id="item-'.$field.'-error">'.e($itemBag->first($field)).'</p>') : '';
      @endphp
      @if ($itemBag->any())<div id="item-errors">@include('manage._errors', ['bag' => $itemBag])</div>@endif
        <div class="mgrid">
          <div>
            <label for="item-category" class="field-label">Category</label>
          {!! $itemMsg('category_id') !!}
            <select id="item-category" name="category_id" required {!! $itemInvalid('category_id') !!}>
              @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($itemOld && (int) old('category_id') === $category->id)>{{ $category->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="item-name" class="field-label">Name</label>
          {!! $itemMsg('name') !!}
            <input id="item-name" name="name" maxlength="60" required {!! $itemInvalid('name') !!} value="{{ $itemOld ? old('name') : '' }}" placeholder="e.g. Bluetooth Speaker">
          </div>
          <div>
            <label for="item-qty" class="field-label">Stock</label>
          {!! $itemMsg('total_qty') !!}
            <input id="item-qty" name="total_qty" type="number" min="0" max="9999" required {!! $itemInvalid('total_qty') !!} value="{{ $itemOld ? old('total_qty') : 1 }}">
          </div>
          <div>
            <label for="item-max" class="field-label">Most per booking</label>
          {!! $itemMsg('max_per_request') !!}
            <input id="item-max" name="max_per_request" type="number" min="1" max="99" required {!! $itemInvalid('max_per_request') !!} value="{{ $itemOld ? old('max_per_request') : 4 }}">
          </div>
        </div>
        <button type="submit" class="btn-small">Add item</button>
      </form>
    </details>
    <details class="add-box" @if ($errors->getBag('category')->any()) open @endif>
      <summary>+ Add a category</summary>
      <form method="post" action="{{ route('manage.categories.store') }}" class="mform">
        @csrf
        @php
        $catBag = $errors->getBag('category');
        $catFocus = collect(['name'])->first(fn ($field) => $catBag->has($field));
        $catInvalid = fn (string $field) => ($catBag->has($field) ? 'aria-invalid="true" aria-describedby="category-'.$field.'-error"' : '').($catFocus === $field ? ' autofocus' : '');
        $catMsg = fn (string $field) => $catBag->has($field) ? new \Illuminate\Support\HtmlString('<p class="field-error" id="category-'.$field.'-error">'.e($catBag->first($field)).'</p>') : '';
      @endphp
      @if ($catBag->any())<div id="category-errors">@include('manage._errors', ['bag' => $catBag])</div>@endif
        <label for="new-category" class="field-label">Name</label>
          {!! $catMsg('name') !!}
        <input id="new-category" name="name" maxlength="60" required {!! $catInvalid('name') !!} value="{{ $errors->getBag('category')->any() ? old('name') : '' }}" placeholder="e.g. Sports Equipment">
        <button type="submit" class="btn-small">Add category</button>
      </form>
    </details>
  </div>
</section>
@endsection
