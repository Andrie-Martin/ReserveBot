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
  <p class="muted">Everyone in {{ $admin->department->name }} who can log in. You make and manage the block accounts and lab staff accounts; each one picks its own password the first time it logs in. A block account belongs to its block, with the block’s representative named on it.</p>
  </header>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif
  @unless ($emailSends)
    <p class="notice" role="note">{{ \App\Support\EmailSetup::logOnlyNotice() }}</p>
  @endunless

  <div class="rb-booking__panel">
  <details class="add-box" @if ($errors->getBag('add')->any()) open @endif>
    <summary>+ Add an account</summary>
    <form method="post" action="{{ route('accounts.store') }}" class="mform acct-form">
      @csrf
      @php
        $addBag = $errors->getBag('add');
        $addFocus = collect(['name', 'email', 'block_id', 'section_id', 'password'])->first(fn ($field) => $addBag->has($field));
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
      <p class="hint">We email them a link to connect their email. Block accounts can book once it’s connected.</p>
    </form>
  </details>
  </div>

  @php $total = fn ($title, $people) => isset($groupTotals) ? ($groupTotals[$title] ?? $people->count()) : $people->count(); @endphp
  @foreach ($groups as $title => $people)
    @continue(isset($paginator) && $people->isEmpty() && $total($title, $people) > 0)
    <div class="rb-booking__panel">
    <h2>{{ $title }} <span class="count">{{ $total($title, $people) }}</span></h2>
    @if ($people->isEmpty())
      <p class="empty">None yet.</p>
    @else
      <table class="mtable acct-table">
        <colgroup><col class="c-name"><col class="c-context"><col class="c-status"><col class="c-edit"></colgroup>
        <thead>
          <tr>
            <th>{{ $title === 'Block accounts' ? 'Block' : 'Name' }}</th>
            {{-- Admins have no block or section: the column stays (empty) so name, status and action line up with the other groups. --}}
            @if ($title !== 'Admins')
              <th>{{ $title === 'Block accounts' ? 'Representative' : 'Section' }}</th>
            @else
              <th><span class="sr-only">No extra details for admins</span></th>
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
                    <strong>{{ $person->displayName() }}</strong>@if ($person->is($admin)) <span class="tag-shared">you</span>@endif
                    <span class="acct-email">{{ $person->email }}</span>
                  </span>
                </span>
              </td>
              @if ($title !== 'Admins')
                <td class="acct-block" data-label="{{ $title === 'Block accounts' ? 'Representative' : 'Section' }}">{{ $title === 'Block accounts' ? $person->name : ($person->section?->name ?? '—') }}</td>
              @else
                <td class="acct-block" aria-hidden="true"></td>
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
                  <a href="{{ route('accounts.edit', $person) }}">Edit<span class="sr-only"> {{ $person->displayName() }}</span></a>
                @elseif ($person->is($admin))
                  <a href="{{ route('account') }}">My account</a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
    @if ($title === 'Admins')
      <p class="hint">Admin accounts are made and looked after by the College admin, not here.</p>
    @endif
    </div>
  @endforeach

  @isset($paginator)
    <x-ui.pager :paginator="$paginator" noun="accounts" />
  @endisset
</section>
@endsection
