@extends('layouts.app')

@section('title', 'Manage equipment')

@section('content')
<section class="card manage-card">
  <h1>Manage</h1>
  @include('manage._tabs')

  <p class="muted">Stock is how many the department has. “Most per booking” is the most one booking can ask for. An item that’s off can’t be booked; bookings already made keep it.</p>

  @foreach ($categories as $category)
    @php $renameBag = $errors->getBag('category'.$category->id); @endphp
    <section class="mcat">
      <div class="mcat-head">
        <h2>{{ $category->name }} <span class="count">{{ $category->equipment->count() }}</span></h2>
        <details class="rename" @if ($renameBag->any()) open @endif>
          <summary>Rename</summary>
          <form method="post" action="{{ route('manage.categories.update', $category) }}" class="inline-form">
            @csrf
            @method('PUT')
            <label for="cat-{{ $category->id }}" class="sr-only">New name for {{ $category->name }}</label>
            <input id="cat-{{ $category->id }}" name="name" maxlength="60" required value="{{ $renameBag->any() ? old('name') : $category->name }}">
            <button type="submit" class="btn-small">Save</button>
          </form>
          @if ($renameBag->any())
            <p class="field-error">{{ $renameBag->first('name') }}</p>
          @endif
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

  <div class="add-row">
    <details class="add-box" @if ($errors->getBag('item')->any()) open @endif>
      <summary>+ Add an item</summary>
      <form method="post" action="{{ route('manage.equipment.store') }}" class="mform">
        @csrf
        @php $itemOld = $errors->getBag('item')->any(); @endphp
        @include('manage._errors', ['bag' => $errors->getBag('item')])
        <div class="mgrid">
          <div>
            <label for="item-category" class="field-label">Category</label>
            <select id="item-category" name="category_id" required>
              @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($itemOld && (int) old('category_id') === $category->id)>{{ $category->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="item-name" class="field-label">Name</label>
            <input id="item-name" name="name" maxlength="60" required value="{{ $itemOld ? old('name') : '' }}" placeholder="e.g. Bluetooth Speaker">
          </div>
          <div>
            <label for="item-qty" class="field-label">Stock</label>
            <input id="item-qty" name="total_qty" type="number" min="0" max="9999" required value="{{ $itemOld ? old('total_qty') : 1 }}">
          </div>
          <div>
            <label for="item-max" class="field-label">Most per booking</label>
            <input id="item-max" name="max_per_request" type="number" min="1" max="99" required value="{{ $itemOld ? old('max_per_request') : 4 }}">
          </div>
        </div>
        <button type="submit" class="btn-small">Add item</button>
      </form>
    </details>
    <details class="add-box" @if ($errors->getBag('category')->any()) open @endif>
      <summary>+ Add a category</summary>
      <form method="post" action="{{ route('manage.categories.store') }}" class="mform">
        @csrf
        @include('manage._errors', ['bag' => $errors->getBag('category')])
        <label for="new-category" class="field-label">Name</label>
        <input id="new-category" name="name" maxlength="60" required value="{{ $errors->getBag('category')->any() ? old('name') : '' }}" placeholder="e.g. Sports Equipment">
        <button type="submit" class="btn-small">Add category</button>
      </form>
    </details>
  </div>
</section>
@endsection
