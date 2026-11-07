@extends('layouts.app')
@section('title', 'Layout examples')
@section('main-class', $mainClass ?? '')
@section('content')
  <h1>Shell rendering example</h1>
  <p>Isolated sample data — not a live account or reservation.</p>
  <div class="card {{ $cardClass ?? '' }}" id="layout-content">
    <p>Existing page typography and controls remain unchanged in Phase 02.</p>
    <form method="post" action="#sample-only" id="layout-form">
      @csrf
      @method('PUT')
      <label for="layout-input">Example input</label>
      <input id="layout-input" name="example" value="Retained value">
      <button type="submit" name="intent" value="save">Example submit</button>
    </form>
  </div>
  <div style="height: 60rem" aria-hidden="true"></div>
  <a href="#layout-input" id="layout-last">Last focusable content</a>
@endsection
