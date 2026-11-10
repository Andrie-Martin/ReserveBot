{{-- The College admin: one department's Admin accounts --}}
@extends('layouts.app')
@use('App\Support\AccountFields')
@use('App\Services\DepartmentSetup')

@section('title', 'Admins of '.$department->name)

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
    <p class="rb-booking__kicker">{{ auth()->user()->roleLabel() }}</p>
    <h1>Admins of {{ $department->name }}</h1>
    <p class="back"><a href="{{ route('college.home') }}">‹ All departments</a></p>
    <p class="muted">The people who run this department in ReserveBot. An Admin makes the department’s lab staff and block accounts, manages its rooms, equipment and timetable, and approves bookings.</p>
  </header>

  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif
  @if ($setupLeft)
    <p class="notice">This department isn’t set up yet. Still to add: {{ DepartmentSetup::leftInWords($setupLeft) }}. Its Admin is shown these steps on their home page; blocks can’t book until they’re done.</p>
  @endif
  @unless ($emailSends)
    <p class="notice" role="note">{{ \App\Support\EmailSetup::logOnlyNotice() }}</p>
  @endunless

  <div class="rb-booking__panel">
  <details class="add-box" @if ($errors->getBag('add')->any()) open @endif>
    <summary>+ Add an admin</summary>
    <form method="post" action="{{ route('college.admins.store', $department) }}" class="mform acct-form">
      @csrf
      @php
        $addBag = $errors->getBag('add');
        $addFocus = collect(['name', 'email', 'password'])->first(fn ($field) => $addBag->has($field));
        $invalid = fn (string $field) => ($addBag->has($field) ? 'aria-invalid="true" aria-describedby="add-errors"' : '').($addFocus === $field ? ' autofocus' : '');
      @endphp
      @if ($addBag->any())<div id="add-errors">@include('manage._errors', ['bag' => $addBag])</div>@endif
      <div class="mgrid">
        <div>
          <label for="add-name" class="field-label">Name</label>
          <input id="add-name" name="name" maxlength="100" required {!! $invalid('name') !!} value="{{ $addBag->any() ? old('name') : '' }}" placeholder="e.g. Juan Dela Cruz">
        </div>
        <div>
          <label for="add-email" class="field-label">Email</label>
          <input id="add-email" name="email" type="email" maxlength="150" required {!! $invalid('email') !!} value="{{ $addBag->any() ? old('email') : '' }}">
        </div>
        <div>
          <label for="add-password" class="field-label">Starting password</label>
          <input id="add-password" name="password" type="text" minlength="8" maxlength="64" required autocomplete="off" spellcheck="false" autocapitalize="off" {!! $invalid('password') !!}>
        </div>
      </div>
      <p class="hint">{{ AccountFields::PASSWORD_HINT }} Give the starting password to them in person or by chat; they’ll pick their own when they first log in.</p>
      <button type="submit" class="btn-small">Make admin account</button>
      <p class="hint">We email them a link to connect their email.</p>
    </form>
  </details>
  </div>

  @if ($otherAdmins->isNotEmpty())
    {{-- Assign an Admin of another department to this one. The same can be done from that Admin's own page. --}}
    <div class="rb-booking__panel" id="move">
    <h2>Move an existing Admin here</h2>
    <form method="post" action="{{ route('college.admins.move-here', $department) }}" class="mform">
      @csrf
      @php $moveBag = $errors->getBag('move'); @endphp
      @if ($moveBag->any())<div id="move-errors">@include('manage._errors', ['bag' => $moveBag])</div>@endif
      <div class="inline-form">
        <label for="move-account" class="sr-only">Admin to move here</label>
        <select id="move-account" name="account_id" required @if ($moveBag->any()) autofocus aria-invalid="true" aria-describedby="move-errors" @endif>
          <option value="">Pick an Admin…</option>
          @foreach ($otherAdmins as $person)
            <option value="{{ $person->id }}" @selected((int) old('account_id') === $person->id)>{{ $person->name }} · now in {{ $person->department->name }}@unless ($person->is_active) (turned off)@endunless</option>
          @endforeach
        </select>
        <button type="submit" class="btn-small">Move here</button>
      </div>
      <p class="hint">The Admin keeps the same account, email and password. From their next click they run {{ $department->name }} and no longer see their old department. We email them about it.</p>
    </form>
    </div>
  @endif

  <div class="rb-booking__panel">
    <h2>Admins <span class="count">{{ $admins->count() }}</span></h2>
    @if ($admins->isEmpty())
      <p class="empty">None yet. Use “+ Add an admin” above to make this department’s first Admin.</p>
    @else
      <table class="mtable acct-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th><span class="sr-only">Edit</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($admins as $person)
            <tr @class(['off' => ! $person->is_active])>
              <td>
                <span class="acct-name">
                  <x-avatar :user="$person" />
                  <span>
                    <strong>{{ $person->name }}</strong>
                    <span class="acct-email">{{ $person->email }}</span>
                  </span>
                </span>
              </td>
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
              <td class="acct-edit"><a href="{{ route('college.admins.edit', $person) }}">Edit<span class="sr-only"> {{ $person->name }}</span></a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="rb-booking__panel" id="details">
  <h2>Department details</h2>
  <form method="post" action="{{ route('college.departments.update', $department) }}" class="mform acct-form">
    @csrf
    @method('PUT')
    @php
      $departmentBag = $errors->getBag('department');
      $departmentFocus = collect(['name', 'contact_email'])->first(fn ($field) => $departmentBag->has($field));
      $invalidDetail = fn (string $field) => ($departmentBag->has($field) ? 'aria-invalid="true" aria-describedby="department-errors"' : '').($departmentFocus === $field ? ' autofocus' : '');
    @endphp
    @if ($departmentBag->any())<div id="department-errors">@include('manage._errors', ['bag' => $departmentBag])</div>@endif
    <div class="mgrid">
      <div>
        <label for="department-name" class="field-label">Department name</label>
        <input id="department-name" name="name" maxlength="100" required {!! $invalidDetail('name') !!} value="{{ $departmentBag->any() ? old('name') : $department->name }}">
      </div>
      <div>
        <label for="department-email" class="field-label">Contact email <span class="optional">(optional)</span></label>
        <input id="department-email" name="contact_email" type="email" maxlength="150" {!! $invalidDetail('contact_email') !!} value="{{ $departmentBag->any() ? old('contact_email') : $department->contact_email }}">
      </div>
    </div>
    <button type="submit" class="btn-small">Save</button>
    <p class="hint">The name shows on every page of this department’s accounts.</p>
  </form>
  </div>
</section>
@endsection
