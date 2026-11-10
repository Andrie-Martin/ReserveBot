{{-- The College admin: one department Admin account (details, a new password, delete) --}}
@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'Edit '.$account->name)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
    <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
    <h1>Edit {{ $account->name }}</h1>
    <p class="back"><a href="{{ route('college.departments.show', $account->department) }}">‹ Admins of {{ $account->department->name }}</a></p>
  </header>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  <div class="rb-booking__panel">
  <div class="acct-head">
    <x-avatar :user="$account" size="md" />
    <div>
      <p class="edit-title">{{ $account->name }}</p>
      <p class="muted small acct-meta">
        <span class="meta-unit">{{ $account->roleLabel() }}</span>
        <span class="meta-unit">{{ $account->department->name }}</span>
      </p>
      <p class="acct-status">
        @if (! $account->is_active)
          <span class="status-badge st-cancelled">Off</span>
        @endif
        @if ($account->must_change_password)
          <span class="tag-soft">Hasn’t picked a password</span>
        @endif
        @if ($account->hasConnectedEmail())
          <span class="tag-ok">Email connected</span>
        @else
          <span class="tag-warn">Email not connected</span>
        @endif
      </p>
    </div>
  </div>
  </div>

  <div class="rb-booking__panel">
  <h2>Details</h2>
  <form method="post" action="{{ route('college.admins.update', $account) }}" class="mform acct-form">
    @csrf
    @method('PUT')
    @php
      $detailsBag = $errors->getBag('details');
      $detailsFocus = collect(['name', 'email'])->first(fn ($field) => $detailsBag->has($field));
      $invalid = fn (string $field) => ($detailsBag->has($field) ? 'aria-invalid="true" aria-describedby="edit-errors"' : '').($detailsFocus === $field ? ' autofocus' : '');
    @endphp
    @if ($detailsBag->any())<div id="edit-errors">@include('manage._errors', ['bag' => $detailsBag])</div>@endif
    <div class="mgrid">
      <div>
        <label for="edit-name" class="field-label">Name</label>
        <input id="edit-name" name="name" maxlength="100" required {!! $invalid('name') !!} value="{{ old('name', $account->name) }}">
      </div>
      <div>
        <label for="edit-email" class="field-label">Email</label>
        <input id="edit-email" name="email" type="email" maxlength="150" required {!! $invalid('email') !!} value="{{ old('email', $account->email) }}">
      </div>
    </div>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($detailsBag->any() ? old('is_active') : $account->is_active)> Can log in <span class="optional">(untick to turn the account off)</span></label>
    @if ($account->is_active && $otherActiveAdmins === 0)
      <p class="hint">This is the only Admin of {{ $account->department->name }} who can log in. Turning the account off leaves the department with nobody to run it.</p>
    @endif
    <button type="submit" class="btn-small">Save</button>
    <p class="hint">A new email has to be connected again: we email it a link.</p>
  </form>
  </div>

  <div class="rb-booking__panel">
  <h2>Set a new password</h2>
  <form method="post" action="{{ route('college.admins.password', $account) }}" class="mform">
    @csrf
    @method('PUT')
    @php $passwordBag = $errors->getBag('password'); @endphp
    @if ($passwordBag->any())<div id="password-errors">@include('manage._errors', ['bag' => $passwordBag])</div>@endif
    <div class="inline-form">
      <label for="new-password" class="sr-only">New password</label>
      <input id="new-password" name="password" type="text" minlength="8" maxlength="64" required autocomplete="off" spellcheck="false" autocapitalize="off" placeholder="New password" @if ($passwordBag->any()) autofocus aria-invalid="true" aria-describedby="password-errors" @endif>
      <button type="submit" class="btn-small">Set password</button>
    </div>
    <p class="hint">For an Admin who is locked out. {{ AccountFields::PASSWORD_HINT }} Give it to {{ $account->name }}; they’ll pick their own when they next log in. Any device logged in to the account is logged out.</p>
  </form>
  </div>

  <div class="danger-box">
    <h2>Delete account</h2>
    @if ($hasHistory)
      <p class="hint">{{ $account->name }} has approved, rejected or changed bookings, so deleting turns the account off instead: it can’t log in, and that history stays.</p>
    @else
      <p class="hint">{{ $account->name }} has no history, so the account is deleted for good.</p>
    @endif
    {{-- Js::from writes the question as safe JavaScript, whatever characters the name has --}}
    <form method="post" action="{{ route('college.admins.destroy', $account) }}"
          onsubmit="return confirm({{ \Illuminate\Support\Js::from(($hasHistory ? 'Turn off ' : 'Delete ').$account->name.'’s account?') }})">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn-danger">{{ $hasHistory ? 'Delete (turns it off)' : 'Delete account' }}</button>
    </form>
  </div>
</section>
@endsection
