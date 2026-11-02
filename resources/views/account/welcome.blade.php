{{-- Phase 9: first login, or the next login after an admin set a password: pick your own first --}}
@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'Pick your own password')

@section('content')
<section class="card narrow welcome-card">
  <h1>Hi, {{ $user->name }}!</h1>
  <p class="muted">Your admin gave you a starting password. Pick your own before you continue; the starting one stops working.</p>

  @include('manage._errors', ['bag' => $errors->getBag('default')])

  <form method="post" action="{{ route('welcome.save') }}">
    @csrf
    @method('PUT')
    @if ($askSchoolId)
      <label>
        School ID number @if ($user->role === 'lab_staff')<span class="optional">(optional)</span>@endif
        <input name="school_id" maxlength="20" value="{{ old('school_id') }}" placeholder="e.g. 2021-00123-MN-0" @required($user->role === 'rep') spellcheck="false" autocapitalize="characters">
        <span class="hint">Your PUP student number.</span>
      </label>
    @endif
    <label>
      New password
      <input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="64" required autofocus>
      <span class="hint">{{ AccountFields::PASSWORD_HINT }}</span>
    </label>
    <label>
      Type it again
      <input type="password" name="password_confirmation" autocomplete="new-password" maxlength="64" required>
    </label>
    <button type="submit">Save and continue</button>
  </form>
</section>
@endsection
