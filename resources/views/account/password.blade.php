@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'My password')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<section class="rb-account-page rb-ui rb-stack" aria-labelledby="account-heading">
  @include('account._hero', ['description' => 'Change the password for your current account.'])
  @include('account._tabs')
  <div class="rb-account-password-layout">
  <x-ui.card class="rb-stack rb-account-password-card">
    <h2 class="rb-heading rb-heading--h2">Change your password</h2>
    <form method="post" action="{{ route('password.change') }}" class="rb-stack rb-account-password">
      @csrf
      @method('PUT')
      <x-ui.error-summary :bag="$errors->getBag('default')" />
<x-ui.field field-id="current_password" label="Current password" :error="$errors->getBag('default')->first('current_password')">
  <x-ui.input type="password" name="current_password" autocomplete="current-password" required />
</x-ui.field>
<x-ui.field field-id="password" label="New password" :error="$errors->getBag('default')->first('password')" :hint="AccountFields::PASSWORD_HINT">
  <x-ui.input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="64" required />
</x-ui.field>
<x-ui.field field-id="password_confirmation" label="Type the new password again" :error="$errors->getBag('default')->first('password_confirmation')">
  <x-ui.input type="password" name="password_confirmation" autocomplete="new-password" maxlength="64" required />
</x-ui.field>
      <div class="rb-account-actions"><x-ui.button type="submit">Change password</x-ui.button></div>
    </form>
    <p class="rb-description rb-account-fineprint">Any other device logged in to your account is logged out when you change it. Forgot your current password? Log out and use “Forgot your password?”, or ask your admin.</p>
  </x-ui.card>
  <aside class="rb-card rb-account-guidance" aria-labelledby="password-guidance-title">
    <h2 class="rb-heading rb-heading--h2" id="password-guidance-title">Choosing a password</h2>
    <ul class="rb-account-guidance__list">
      <li>Use <strong>at least 8 characters</strong> (up to 64), with letters and numbers.</li>
      <li>It must be <strong>different from your current password</strong>.</li>
      <li>A phrase you can remember is easier than a short, complicated word.</li>
      <li>Do not reuse a password from another site, and do not share it. Your admin never needs it.</li>
    </ul>
    <p class="rb-description rb-account-fineprint">You will type it twice so a typing slip is caught before it is saved.</p>
  </aside>
  </div>
</section>
@endsection
