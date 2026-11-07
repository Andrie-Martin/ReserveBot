@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'Pick a new password')

@section('content')
<section class="card narrow">
  <h1>Pick a new password</h1>
  <p class="muted">For your ReserveBot account.</p>

  @include('manage._errors', ['bag' => $errors->getBag('default')])

  <form method="post" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label>
      Email
      <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" required>
    </label>
    <label>
      New password
      <input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="64" required autofocus>
      <span class="hint">{{ AccountFields::PASSWORD_HINT }}</span>
    </label>
    <label>
      Type it again
      <input type="password" name="password_confirmation" autocomplete="new-password" maxlength="64" required>
    </label>
    <button type="submit">Save my new password</button>
  </form>
</section>
@endsection
