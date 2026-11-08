{{-- Phase 9 (admins): the department's accounts --}}
@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'Accounts')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
  <p class="rb-booking__kicker">{{ $admin->roleLabel() }}</p>
  <h1>Accounts</h1>
  <p class="muted">Everyone in {{ $admin->department->name }} who can log in. You make and manage the block rep and lab staff accounts; each person picks their own password the first time they log in.</p>
  </header>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif
  @unless ($emailSends)
    <p class="notice">Emails aren’t sent yet: the site writes them to <code>storage/logs/laravel.log</code> instead (MAIL_MAILER=log). Set up Gmail to really send them: see the README, “Email”.</p>
  @endunless

  <div class="rb-booking__panel">
  <details class="add-box" @if ($errors->getBag('add')->any()) open @endif>
    <summary>+ Add an account</summary>
    <form method="post" action="{{ route('accounts.store') }}" class="mform acct-form">
      @csrf
      @php
        $addBag = $errors->getBag('add');
        $addFocus = collect(['name', 'email', 'block_id', 'section_id', 'school_id', 'password'])->first(fn ($field) => $addBag->has($field));
      @endphp
      @if ($addBag->any())<div id="add-errors">@include('manage._errors', ['bag' => $addBag])</div>@endif
      @include('accounts._fields', ['prefix' => 'add', 'account' => null, 'bag' => $addBag, 'focus' => $addFocus])
      <div class="mgrid one">
        <div>
          <label for="add-password" class="field-label">Starting password</label>
          <input id="add-password" name="password" type="text" minlength="8" maxlength="64" required autocomplete="off" spellcheck="false" autocapitalize="off" @if ($addBag->has('password')) aria-invalid="true" aria-describedby="add-errors" @endif @if ($addFocus === 'password') autofocus @endif>
          <p class="hint">{{ AccountFields::PASSWORD_HINT }} Give it to them in person or by chat; they’ll pick their own when they first log in.</p>
        </div>
      </div>
      <button type="submit" class="btn-small">Make account</button>
      <p class="hint">We email them a link to connect their email. Block reps can book once it’s connected.</p>
    </form>
  </details>
  </div>

  @foreach ($groups as $title => $people)
    <div class="rb-booking__panel">
    <h2>{{ $title }} <span class="count">{{ $people->count() }}</span></h2>
    @if ($people->isEmpty())
      <p class="empty">None yet.</p>
    @else
      <table class="mtable acct-table">
        <thead>
          <tr>
            <th>Name</th>
            @if ($title !== 'Admins')
              <th>{{ $title === 'Block reps' ? 'Block' : 'Section' }}</th>
              <th class="hide-sm">School ID</th>
            @endif
            <th>Status</th>
            <th><span class="sr-only">Edit</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($people as $person)
            <tr @class(['off' => ! $person->is_active])>
              <td>
                <span class="acct-name">
                  <x-avatar :user="$person" />
                  <span>
                    <strong>{{ $person->name }}</strong>@if ($person->is($admin)) <span class="tag-shared">you</span>@endif
                    <span class="acct-email">{{ $person->email }}</span>
                  </span>
                </span>
              </td>
              @if ($title !== 'Admins')
                <td class="acct-block" data-label="{{ $title === 'Block reps' ? 'Block' : 'Section' }}">{{ $person->section?->name ?? '—' }}</td>
                <td class="hide-sm">{{ $person->school_id ?? '—' }}</td>
              @endif
              <td class="acct-status">
                @if (! $person->is_active)
                  <span class="status-badge st-cancelled">Off</span>
                @else
                  <span class="status-badge st-confirmed">On</span>
                @endif
                @if ($person->must_change_password)
                  <span class="tag-soft">Hasn’t picked a password</span>
                @endif
                @if (! $person->hasConnectedEmail())
                  <span class="tag-warn">Email not connected</span>
                @endif
              </td>
              <td class="acct-edit">
                @if (in_array($person->role, \App\Models\User::MANAGED_ROLES, true))
                  <a href="{{ route('accounts.edit', $person) }}">Edit<span class="sr-only"> {{ $person->name }}</span></a>
                @elseif ($person->is($admin))
                  <a href="{{ route('account') }}">My account</a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
    </div>
  @endforeach
</section>
@endsection
