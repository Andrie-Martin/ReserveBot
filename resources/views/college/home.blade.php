{{-- The College admin's home: every department at a glance, and the way in to each department's Admin accounts --}}
@extends('layouts.app')

@section('title', 'Departments')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/manage.css') }}?v={{ filemtime(public_path('css/manage.css')) }}">
@endpush

@section('content')
<section class="card manage-card rb-booking rb-manage rb-ui">
  <header class="rb-booking__intro">
    <p class="rb-booking__kicker">{{ $user->roleLabel() }}</p>
    <h1>Departments</h1>
    <p class="muted">Every department that uses ReserveBot. You make and look after each department’s Admin accounts. The Admins run their own department: its lab staff and block accounts, rooms, equipment, timetable and bookings.</p>
  </header>

  @include('home._login-notice')
  @if (session('status'))
    <p class="success" role="status">{{ session('status') }}</p>
  @endif
  @if (session('problem'))
    <p class="error" role="alert">{{ session('problem') }}</p>
  @endif

  @forelse ($departments as $department)
    <div class="rb-booking__panel college-dept" id="department-{{ $department->id }}">
      <div class="college-dept__head">
        <h2>{{ $department->name }}</h2>
        <x-ui.link-button variant="secondary" size="compact" :href="route('college.departments.show', $department)">Admin accounts<span class="sr-only"> of {{ $department->name }}</span></x-ui.link-button>
      </div>
      @if ($department->active_admins_count === 0)
        <p class="error" role="note">{{ $department->admins_count === 0 ? 'This department has no Admin yet.' : 'No Admin of this department can log in.' }} Nobody can approve its accounts or manage it until one is added or turned back on.</p>
      @endif
      <dl class="bfacts">
        <div class="bfact"><dt>Admins</dt><dd>{{ $department->admins_count }}@if ($department->admins_count > $department->active_admins_count) <span class="muted">({{ $department->admins_count - $department->active_admins_count }} turned off)</span>@endif</dd></div>
        <div class="bfact"><dt>Lab staff</dt><dd>{{ $department->staff_count }}</dd></div>
        <div class="bfact"><dt>Block accounts</dt><dd>{{ $department->blocks_count }}</dd></div>
        <div class="bfact"><dt>Rooms</dt><dd>{{ $department->rooms_count }}</dd></div>
        <div class="bfact"><dt>Requests waiting for approval</dt><dd>{{ (int) ($waiting[$department->id] ?? 0) }}</dd></div>
      </dl>
    </div>
  @empty
    <div class="rb-booking__panel">
      <p class="empty">No department has been set up yet. Departments are added by whoever runs ReserveBot (README, “Adding a department”).</p>
    </div>
  @endforelse

  <p class="hint">The numbers are for information only. To approve a booking or change a room, a department’s own Admin or lab staff logs in.</p>
</section>
@endsection
