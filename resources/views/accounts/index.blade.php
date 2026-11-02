{{-- Phase 9 (admins): the department's accounts --}}
@extends('layouts.app')
@use('App\Support\AccountFields')

@section('title', 'Accounts')

@section('content')
<section class="card manage-card">
  <h1>Accounts</h1>
  <p class="muted">Everyone in {{ $admin->department->name }} who can log in. You make and manage the block rep and lab staff accounts; each person picks their own password the first time they log in.</p>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif
  @unless ($emailSends)
    <p class="notice">Emails aren’t sent yet: the site writes them to <code>storage/logs/laravel.log</code> instead (MAIL_MAILER=log). Set up Gmail to really send them: see the README, “Email”.</p>
  @endunless

  <details class="add-box" @if ($errors->getBag('add')->any()) open @endif>
    <summary>+ Add an account</summary>
    <form method="post" action="{{ route('accounts.store') }}" class="mform acct-form">
      @csrf
      @include('manage._errors', ['bag' => $errors->getBag('add')])
      @include('accounts._fields', ['prefix' => 'add', 'account' => null])
      <div class="mgrid one">
        <div>
          <label for="add-password" class="field-label">Starting password</label>
          <input id="add-password" name="password" type="text" minlength="8" maxlength="64" required autocomplete="off" spellcheck="false" autocapitalize="off">
          <p class="hint">{{ AccountFields::PASSWORD_HINT }} Give it to them in person or by chat; they’ll pick their own when they first log in.</p>
        </div>
      </div>
      <button type="submit" class="btn-small">Make account</button>
      <p class="hint">We email them a link to connect their email. Block reps can book once it’s connected.</p>
    </form>
  </details>

  @foreach ($groups as $title => $people)
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
                <td class="acct-block">{{ $person->section?->name ?? '—' }}</td>
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
  @endforeach
</section>
@endsection
