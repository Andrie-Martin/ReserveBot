@extends('layouts.app')

@section('title', 'Log in')
@section('main-class', 'rb-auth-main--login')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<div class="rb-auth-login rb-ui">
  <section class="rb-auth-login__content" aria-labelledby="login-heading">
    <div class="rb-auth-login__intro">
      <p class="rb-auth-eyebrow">CEA reservation portal</p>
      <h1 id="login-heading" class="rb-heading rb-heading--display">Make room for your<br class="rb-auth-desktop-break"> <span>next big idea.</span></h1>
      <p class="rb-description">Reserve classrooms, laboratory spaces, and equipment through one straightforward departmental workflow.</p>
    </div>
    <x-ui.auth-card class="rb-auth-login__card">
      <x-slot:heading>
        <h2 class="rb-heading rb-heading--h2">Log in to ReserveBot</h2>
        <p class="rb-description">Use your department-assigned account. Book rooms and equipment for your class.</p>
      </x-slot:heading>
      @if (session('status'))
  <x-ui.alert tone="success" role="status">{{ session('status') }}</x-ui.alert>
@endif
      <form method="post" action="{{ route('login') }}" class="rb-stack rb-auth-form">
        @csrf
        <x-ui.field field-id="email" label="Email address" :error="$errors->getBag('default')->first('email')">
  <x-ui.input type="email" name="email" :value="old('email')" autocomplete="username" required autofocus />
</x-ui.field>
        <x-ui.field field-id="password" label="Password" :error="$errors->getBag('default')->first('password')">
  <x-ui.input type="password" name="password" autocomplete="current-password" required />
</x-ui.field>
        <x-ui.button type="submit">Log in</x-ui.button>
      </form>
      <x-slot:footer><a href="{{ route('password.request') }}">Forgot your password?</a></x-slot:footer>
    </x-ui.auth-card>
    <p class="rb-auth-disclaimer">Unofficial student design · Not an official PUP website</p>
  </section>
  <div class="rb-auth-login__art" aria-hidden="true">@include('auth._illustration')</div>
</div>
@endsection
