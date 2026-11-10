@extends('layouts.app')

@section('title', 'Log in')
@section('main-class', 'rb-auth-main--login')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/login-mascot.css') }}?v={{ filemtime(public_path('css/login-mascot.css')) }}">
  <script src="{{ asset('js/login-mascot.js') }}?v={{ filemtime(public_path('js/login-mascot.js')) }}" defer></script>
@endpush

@section('content')
<div class="rb-auth-login rb-ui">
  <section class="rb-auth-login__content" aria-labelledby="login-heading">
    <x-ui.login-mascot class="rb-login-mascot--card" />
    <x-ui.auth-card class="rb-auth-login__card">
      <x-slot:heading>
        <p class="rb-auth-eyebrow">Authorized access</p>
        <h1 id="login-heading" class="rb-heading rb-heading--h2">Log in to ReserveBot</h1>
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
        {{-- After a refused attempt the password box must stay empty. A browser that has this account's password saved
             would otherwise put it straight back, and pressing "Log in" again would log in with the saved password, as if
             the wrong one had been accepted (QA AUTH-01). autocomplete="new-password" is the standard way to tell
             browsers not to fill a saved password in; the first visit keeps "current-password", so saved logins still work. --}}
        @php $retry = $errors->getBag('default')->any(); @endphp
        <x-ui.field field-id="password" label="Password" :error="$errors->getBag('default')->first('password')">
  <x-ui.input type="password" name="password" :autocomplete="$retry ? 'new-password' : 'current-password'" required />
</x-ui.field>
        <x-ui.button type="submit">Log in</x-ui.button>
      </form>
      <x-slot:footer>
        <a href="{{ route('password.request') }}">Forgot your password?</a>
        <p class="rb-auth-fineprint">No public registration. Your admin creates or manages accounts.</p>
      </x-slot:footer>
    </x-ui.auth-card>
  </section>
  @include('auth._aside')
</div>
@endsection
