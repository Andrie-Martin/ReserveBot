@extends('layouts.app')

@section('title', 'Manage equipment')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
  <script src="{{ asset('js/manage.js') }}?v={{ filemtime(public_path('js/manage.js')) }}" defer></script>
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Manage equipment</h1>
  @include('manage._tabs')

  <p class="muted">Stock is how many the department has. “Most per booking” is the most one booking can ask for. An item that’s off can’t be booked; bookings already made keep it.</p>
  </header>

  <div class="add-row rb-booking__panel">
    @if ($categories->isNotEmpty())
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
    @endif
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
  @if ($categories->isNotEmpty())
    <div class="rb-booking__panel eq-search" data-eq-search hidden>
      <label for="eq-filter" class="field-label">Find equipment</label>
      <div class="rb-search">
        <input id="eq-filter" type="search" class="rb-search__input" placeholder="Search by item or category" autocomplete="off" aria-controls="eq-categories" aria-describedby="eq-filter-status">
        <button type="button" class="rb-search__clear" aria-label="Clear search" aria-controls="eq-filter" hidden><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
      </div>
      <p class="hint" id="eq-filter-status" role="status" aria-live="polite"></p>
    </div>
  @endif

  <div id="eq-categories">
  @if ($categories->isEmpty())
    <div class="rb-booking__panel">
      <p class="empty">No equipment yet. Add a category first (for example “Audiovisual Equipment”), then add items to it.</p>
    </div>
  @endif

  @foreach ($categories as $category)
    @php $renameBag = $errors->getBag('category'.$category->id); @endphp
    <section class="mcat rb-booking__panel" data-eq-section>
      <div class="mcat-head">
        <h2 class="mcat-title" id="cat-title-{{ $category->id }}">
          <a class="mcat-name-link" href="#cat-rename-{{ $category->id }}" data-rb-rename="cat-rename-{{ $category->id }}" aria-describedby="cat-hint-{{ $category->id }}" title="Double-click to rename"><span class="mcat-name" data-eq-category>{{ $category->name }}</span><svg class="mcat-pencil" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M11.5 2.5l2 2-8 8-3 1 1-3 8-8z"/></svg></a>
          <span class="count">{{ $category->equipment->count() }}</span>
        </h2>
        <span class="mcat-hint" aria-hidden="true"><span class="mcat-hint__fine">Double-click the name to rename</span><span class="mcat-hint__coarse">Tap the name to rename</span></span>
        <span class="sr-only" id="cat-hint-{{ $category->id }}">Rename this category: double-click it, tap it, or press Enter or F2.</span>
        <form method="post" action="{{ route('manage.categories.update', $category) }}" id="cat-rename-{{ $category->id }}" @class(['mcat-rename', 'rb-reveal', 'is-open' => $renameBag->any()]) data-original="{{ $category->name }}" novalidate>
          @csrf
          @method('PUT')
          <label for="cat-{{ $category->id }}" class="sr-only">New name for {{ $category->name }}</label>
          <input id="cat-{{ $category->id }}" name="name" maxlength="60" required data-rb-focus @if ($renameBag->any()) autofocus aria-invalid="true" aria-describedby="cat-{{ $category->id }}-error" @endif value="{{ $renameBag->any() ? old('name') : $category->name }}">
          <button type="submit" class="btn-small">Save<span class="sr-only"> name</span></button>
          <a class="btn-ghost" href="#cat-title-{{ $category->id }}" data-rb-reveal-close>Cancel</a>
          <p class="field-error" id="cat-{{ $category->id }}-error" role="alert" @if (! $renameBag->any()) hidden @endif>{{ $renameBag->first('name') }}</p>
        </form>
      </div>
      @if ($category->equipment->isEmpty())
        <p class="empty">No items yet.</p>
      @else
        <table class="mtable eq-table">
          <colgroup><col class="c-item"><col class="c-stock"><col class="c-max"><col class="c-status"><col class="c-edit"></colgroup>
          <thead><tr><th>Item</th><th>Stock</th><th>Most per booking</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @foreach ($category->equipment as $item)
              <tr @class(['off' => ! $item->is_active]) data-eq-row>
                <td class="eq-name" data-eq-name>{{ $item->name }}</td>
                <td>{{ $item->total_qty }}</td>
                <td>{{ $item->max_per_request }}</td>
                <td><span @class(['status-badge', 'st-confirmed' => $item->is_active, 'st-cancelled' => ! $item->is_active])>{{ $item->is_active ? 'On' : 'Off' }}</span></td>
                <td class="eq-edit"><div class="row-actions"><a class="row-edit" href="{{ route('manage.equipment.edit', $item) }}">Edit<span class="sr-only"> {{ $item->name }}</span></a>@if (\App\Support\ResourceDeletion::equipmentLooksDeletable($item))<a class="row-delete" href="{{ route('manage.equipment.edit', $item) }}#delete">Delete<span class="sr-only"> {{ $item->name }}: review and confirm on its page</span></a>@endif</div></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </section>
  @endforeach
  <p class="empty rb-booking__panel" data-eq-none hidden>No equipment or category matches that search.</p>
  </div>
</section>
@endsection
