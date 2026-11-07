@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<section class="card narrow">
  <h1>Log in</h1>
  <p class="muted">Book rooms and equipment for your class.</p>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @error('email')
    <p class="error">{{ $message }}</p>
  @enderror

  <form method="post" action="{{ route('login') }}">
    @csrf
    <label>
      Email
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    </label>
    <label>
      Password
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit">Log in</button>
  </form>
  <p class="form-foot"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
</section>
@endsection
