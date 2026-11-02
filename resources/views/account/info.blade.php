{{-- Phase 9: My account, Information tab. Staff use the photo, name, block and School ID to tell people apart. --}}
@extends('layouts.app')

@section('title', 'My account')

@section('content')
<section class="card manage-card account-card">
  <h1>My account</h1>
  @include('account._tabs')

  {{-- Photo --}}
  <div class="photo-box">
    <x-avatar :user="$user" size="lg" />
    <div class="photo-side">
      <p class="photo-name">{{ $user->name }}</p>
      <p class="muted small">{{ $user->roleLabel() }} · {{ $user->department->name }}</p>
      @include('manage._errors', ['bag' => $errors->getBag('photo')])
      <div class="photo-forms">
        <form method="post" action="{{ route('account.photo') }}" enctype="multipart/form-data">
          @csrf
          <label for="photo" class="sr-only">Photo</label>
          <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
          <button type="submit" class="btn-small">{{ $user->photo_path ? 'Replace photo' : 'Upload photo' }}</button>
        </form>
        @if ($user->photo_path)
          <form method="post" action="{{ route('account.photo.remove') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="link">Remove photo</button>
          </form>
        @endif
      </div>
      <p class="hint">A clear photo of your face (JPG, PNG or WebP, up to 5 MB). Lab staff see it when you pick up a key.</p>
    </div>
  </div>

  {{-- Email connection --}}
  <div @class(['email-state', 'is-connected' => $user->hasConnectedEmail()])>
    @if ($user->hasConnectedEmail())
      <p><span class="tag-ok">Connected</span> Your email {{ $user->email }} is connected{{ $user->email_verified_at ? ' since '.$user->email_verified_at->format('F j, Y') : '' }}.</p>
    @else
      <p><span class="tag-warn">Not connected yet</span> Open the email we sent to <strong>{{ $user->email }}</strong> and click “Connect my email”.{{ $user->role === 'rep' ? ' You can book once it’s connected.' : '' }}</p>
      <form method="post" action="{{ route('email.resend') }}">
        @csrf
        <button type="submit" class="btn-small btn-quiet">Send the link again</button>
      </form>
    @endif
    @if (session('connect_note'))
      <p class="email-note" role="status">{{ session('connect_note') }}</p>
    @endif
  </div>

  {{-- Information --}}
  <h2>Your information</h2>
  <form method="post" action="{{ route('account.update') }}" class="mform">
    @csrf
    @method('PUT')
    @include('manage._errors', ['bag' => $errors->getBag('info')])
    <div class="mgrid">
      <div>
        <label for="name" class="field-label">Name</label>
        <input id="name" name="name" maxlength="100" required value="{{ old('name', $user->name) }}" autocomplete="name">
      </div>
      <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" maxlength="150" required value="{{ old('email', $user->email) }}" autocomplete="email">
        @if ($user->role === 'rep')
          <p class="hint">Gmail or PUP webmail (<span class="nowrap">…@iskolarngbayan.pup.edu.ph</span>).</p>
        @endif
      </div>
      <div>
        <label for="current_password" class="field-label">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password">
        <p class="hint">Only needed to change your email.</p>
      </div>

      @if ($user->role !== 'admin')
        <div>
          <label for="school_id" class="field-label">School ID number @if ($user->role === 'lab_staff')<span class="optional">(optional)</span>@endif</label>
          <input id="school_id" name="school_id" maxlength="20" value="{{ old('school_id', $user->school_id) }}" placeholder="e.g. 2021-00123-MN-0" @required($user->role === 'rep') spellcheck="false" autocapitalize="characters">
        </div>
      @endif

      @if ($user->role === 'rep')
        <div>
          <span class="field-label">Program and block</span>
          <p class="readonly-value">{{ $user->section ? $user->program().' · '.$user->section->name : 'No block yet' }}</p>
          <p class="hint">Set by your admin: your block decides which classes you can book for.</p>
        </div>
      @elseif ($user->role === 'lab_staff')
        <div>
          <label for="section_id" class="field-label">Program and section <span class="optional">(optional)</span></label>
          <select id="section_id" name="section_id">
            <option value="">None</option>
            @foreach ($sections as $program => $list)
              <optgroup label="{{ $program }}">
                @foreach ($list as $section)
                  <option value="{{ $section->id }}" @selected((int) old('section_id', $user->section_id) === $section->id)>{{ $section->name }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </select>
          <p class="hint">Your own class section, if you’re a student.</p>
        </div>
      @endif
    </div>
    <button type="submit" class="btn-small">Save</button>
  </form>
</section>
@endsection
