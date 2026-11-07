{{-- Phase 9: after clicking "Connect my email" in the email (or when that link no longer works) --}}
@extends('layouts.app')

@section('title', $ok ? 'Email connected' : 'Link not working')

@section('content')
<section class="card narrow">
  <h1>{{ $ok ? 'Email connected' : 'This link isn’t working' }}</h1>
  <p @class(['success' => $ok, 'error' => ! $ok]) role="status">{{ $message }}</p>
  <p class="form-foot">
    @auth
      <a href="{{ route('account') }}">Go to My account</a>
    @else
      <a href="{{ route('login') }}">Log in</a>
    @endauth
  </p>
</section>
@endsection
