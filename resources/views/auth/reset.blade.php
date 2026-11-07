@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'Pick a new password')
@section('main-class', 'rb-auth-main--login')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<div class="rb-auth-login rb-ui">
<section class="rb-auth-login__content" aria-labelledby="reset-heading">
<x-ui.auth-card class="rb-auth-login__card">
  <x-slot:heading><p class="rb-auth-eyebrow">Password reset</p><h1 id="reset-heading" class="rb-heading rb-heading--h2">Pick a new password</h1><p class="rb-description">For your ReserveBot account.</p></x-slot:heading>
  <x-ui.error-summary :bag="$errors->getBag('default')" />
  <form method="post" action="{{ route('password.update') }}" class="rb-stack rb-auth-form">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-ui.field field-id="email" label="Email" :error="$errors->getBag('default')->first('email')">
  <x-ui.input type="email" name="email" :value="old('email', $email)" autocomplete="username" required />
</x-ui.field>
<x-ui.field field-id="password" label="New password" :error="$errors->getBag('default')->first('password')" :hint="AccountFields::PASSWORD_HINT">
  <x-ui.input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="64" required autofocus />
</x-ui.field>
<x-ui.field field-id="password_confirmation" label="Type it again" :error="$errors->getBag('default')->first('password_confirmation')">
  <x-ui.input type="password" name="password_confirmation" autocomplete="new-password" maxlength="64" required />
</x-ui.field>
    <x-ui.button type="submit">Save my new password</x-ui.button>
  </form>
</x-ui.auth-card>
</section>
@include('auth._aside')
</div>
@endsection
