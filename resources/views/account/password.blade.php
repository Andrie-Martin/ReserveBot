{{-- Phase 9: My account, Password tab --}}
@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'My password')

@section('content')
<section class="card manage-card account-card">
  <h1>My account</h1>
  @include('account._tabs')

  <h2>Change your password</h2>
  <form method="post" action="{{ route('password.change') }}" class="mform password-form">
    @csrf
    @method('PUT')
    @include('manage._errors', ['bag' => $errors->getBag('default')])
    <label for="current_password" class="field-label">Current password</label>
    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>

    <label for="password" class="field-label">New password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="64" required>
    <p class="hint">{{ AccountFields::PASSWORD_HINT }}</p>

    <label for="password_confirmation" class="field-label">Type the new password again</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="64" required>

    <button type="submit" class="btn-small">Change password</button>
  </form>
  <p class="hint">Any other device logged in to your account is logged out when you change it. Forgot your current password? Log out and use “Forgot your password?”, or ask your admin.</p>
</section>
@endsection
