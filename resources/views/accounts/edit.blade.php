{{-- Phase 9 (admins): one rep or lab staff account --}}
@extends('layouts.app')
@use('App\Support\AccountFields')
@use('App\Support\TimeSlots')

@section('title', 'Edit '.$account->name)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
  <h1>Accounts</h1>
  <p class="back"><a href="{{ route('accounts.index') }}">‹ All accounts</a></p>
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
      <h2 class="edit-title">{{ $account->name }}</h2>
      <p class="muted small">
        {{ $account->roleLabel() }}@if ($account->section) · {{ $account->section->name }}@endif
        @if ($account->school_id) · {{ $account->school_id }}@endif
        · {{ $bookingCount === 1 ? '1 booking' : $bookingCount.' bookings' }}
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
  <form method="post" action="{{ route('accounts.update', $account) }}" class="mform acct-form">
    @csrf
    @method('PUT')
    @php
      $detailsBag = $errors->getBag('details');
      $detailsFocus = collect(['name', 'email', 'block_id', 'section_id', 'school_id'])->first(fn ($field) => $detailsBag->has($field));
    @endphp
    @if ($detailsBag->any())<div id="edit-errors">@include('manage._errors', ['bag' => $detailsBag])</div>@endif
    @include('accounts._fields', ['prefix' => 'edit', 'bag' => $detailsBag, 'focus' => $detailsFocus])
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($detailsBag->any() ? old('is_active') : $account->is_active)> Can log in <span class="optional">(untick to turn the account off)</span></label>
    <button type="submit" class="btn-small">Save</button>
    <p class="hint">A new email has to be connected again: we email it a link.</p>
  </form>
  </div>

  <div class="rb-booking__panel">
  <h2>Set a new password</h2>
  <form method="post" action="{{ route('accounts.password', $account) }}" class="mform">
    @csrf
    @method('PUT')
    @php $passwordBag = $errors->getBag('password'); @endphp
    @if ($passwordBag->any())<div id="password-errors">@include('manage._errors', ['bag' => $passwordBag])</div>@endif
    <div class="inline-form">
      <label for="new-password" class="sr-only">New password</label>
      <input id="new-password" name="password" type="text" minlength="8" maxlength="64" required autocomplete="off" spellcheck="false" autocapitalize="off" placeholder="New password" @if ($passwordBag->any()) autofocus aria-invalid="true" aria-describedby="password-errors" @endif>
      <button type="submit" class="btn-small">Set password</button>
    </div>
    <p class="hint">{{ AccountFields::PASSWORD_HINT }} Give it to {{ $account->name }}; they’ll pick their own when they next log in. Any device logged in to the account is logged out.</p>
  </form>
  </div>

  <div class="rb-booking__panel">
  <h2>Upcoming bookings <span class="count">{{ $upcoming->count() }}</span></h2>
  @if (! $account->is_active && $upcoming->isNotEmpty())
    <p class="error">This account is off, but these bookings still hold rooms or items. Open each one to cancel it if it’s no longer needed.</p>
  @endif
  @if ($upcoming->isEmpty())
    <p class="empty">None.</p>
  @else
    <ul class="mlist">
      @foreach ($upcoming as $b)
        <li>
          <span class="mlist-when">{{ $b->start_time->format('D, M j') }} · {{ TimeSlots::span($b->start_time->hour * 60 + $b->start_time->minute, $b->end_time->isSameDay($b->start_time) ? $b->end_time->hour * 60 + $b->end_time->minute : 1440) }}</span>
          <span class="mlist-what">{{ $b->room?->name ?? 'No room' }}: {{ $b->purpose }}</span>
          <span class="status-badge st-{{ strtolower($b->status) }}">{{ $b->statusLabel() }}</span>
          <a href="{{ route('bookings.show', $b) }}#change">Open</a>
        </li>
      @endforeach
    </ul>
  @endif
  </div>

  <div class="danger-box">
    <h2>Delete account</h2>
    @if ($hasHistory)
      <p class="hint">{{ $account->name }} has bookings or history, so deleting turns the account off instead: it can’t log in, and its bookings and history stay.</p>
    @else
      <p class="hint">{{ $account->name }} has no bookings or history, so the account is deleted for good.</p>
    @endif
    {{-- Js::from writes the question as safe JavaScript, whatever characters the name has --}}
    <form method="post" action="{{ route('accounts.destroy', $account) }}"
          onsubmit="return confirm({{ \Illuminate\Support\Js::from(($hasHistory ? 'Turn off ' : 'Delete ').$account->name.'’s account?') }})">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn-danger">{{ $hasHistory ? 'Delete (turns it off)' : 'Delete account' }}</button>
    </form>
  </div>
</section>
@endsection
