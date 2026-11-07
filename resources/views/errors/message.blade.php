{{-- Shared look for the error pages (403, 404, 419, 500) --}}
@extends('layouts.app')

@section('title', $heading)

@section('content')
<section class="card narrow">
  <h1>{{ $heading }}</h1>
  <p class="muted">{{ $text }}</p>
  <p><a href="{{ url('/') }}">Go to my home page</a></p>
</section>
@endsection
