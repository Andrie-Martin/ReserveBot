@extends('layouts.app')

@section('title', 'Forgot your password?')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<x-ui.auth-card>
  <x-slot:heading>
    <h1 class="rb-heading rb-heading--h1">Forgot your password?</h1>
    <p class="rb-description">Type your account’s email. We’ll send it a link to pick a new password.</p>
  </x-slot:heading>
  @if (session('status'))
  <x-ui.alert tone="success" role="status">{{ session('status') }}</x-ui.alert>
@endif
  <form method="post" action="{{ route('password.email') }}" class="rb-stack rb-auth-form">
    @csrf
    <x-ui.field field-id="email" label="Email" :error="$errors->getBag('default')->first('email')">
  <x-ui.input type="email" name="email" :value="old('email')" autocomplete="username" required autofocus />
</x-ui.field>
    <x-ui.button type="submit">Send me a link</x-ui.button>
  </form>
  <x-slot:footer>
    <a href="{{ route('login') }}">‹ Back to log in</a>
    <p class="rb-description">No email coming? Your department’s admin can set a new password for you.</p>
  </x-slot:footer>
</x-ui.auth-card>
@endsection
