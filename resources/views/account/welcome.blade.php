@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'Pick your own password')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<x-ui.auth-card>
  <x-slot:heading><h1 class="rb-heading rb-heading--h1">Hi, {{ $user->name }}!</h1><p class="rb-description">Your admin gave you a starting password. Pick your own before you continue; the starting one stops working.</p></x-slot:heading>
  <x-ui.error-summary :bag="$errors->getBag('default')" />
  <form method="post" action="{{ route('welcome.save') }}" class="rb-stack rb-auth-form">
    @csrf
    @method('PUT')
    @if ($askSchoolId)
      <x-ui.field field-id="school_id" :label="'School ID number'.($user->role === 'lab_staff' ? ' (optional)' : '')" hint="Your PUP student number." :error="$errors->getBag('default')->first('school_id')">
        <x-ui.input name="school_id" maxlength="20" :value="old('school_id')" placeholder="e.g. 2021-00123-MN-0" :required="$user->role === 'rep'" spellcheck="false" autocapitalize="characters" />
      </x-ui.field>
    @endif
    <x-ui.field field-id="password" label="New password" :error="$errors->getBag('default')->first('password')" :hint="AccountFields::PASSWORD_HINT">
  <x-ui.input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="64" required autofocus />
</x-ui.field>
<x-ui.field field-id="password_confirmation" label="Type it again" :error="$errors->getBag('default')->first('password_confirmation')">
  <x-ui.input type="password" name="password_confirmation" autocomplete="new-password" maxlength="64" required />
</x-ui.field>
    <x-ui.button type="submit">Save and continue</x-ui.button>
  </form>
</x-ui.auth-card>
@endsection
