@extends('layouts.app')

@section('title', 'My account')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<section class="rb-account-page rb-ui rb-stack" aria-labelledby="account-heading">
  @include('account._hero', ['description' => 'Update your own information, profile photo, and email connection.'])
  @include('account._tabs')
  {{-- DOM order is the focus order and the visual order: information, email connection, then photo. --}}
  <div class="rb-account-layout">
    <x-ui.card class="rb-stack rb-account-info-card">@include('account._information-form')</x-ui.card>
    @include('account._email-state')
    <x-ui.card class="rb-account-photo-card">@include('account._photo')</x-ui.card>
  </div>
</section>
@endsection
