@extends('layouts.app')

@section('title', 'Forgot your password?')

@section('content')
<section class="card narrow">
  <h1>Forgot your password?</h1>
  <p class="muted">Type your account’s email. We’ll send it a link to pick a new password.</p>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @error('email')
    <p class="error">{{ $message }}</p>
  @enderror

  <form method="post" action="{{ route('password.email') }}">
    @csrf
    <label>
      Email
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    </label>
    <button type="submit">Send me a link</button>
  </form>
  <p class="form-foot"><a href="{{ route('login') }}">‹ Back to log in</a></p>
  <p class="hint">No email coming? Your department’s admin can set a new password for you.</p>
</section>
@endsection
