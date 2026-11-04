@extends('layouts.app')

@section('title', 'My account')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<section class="rb-account-page rb-ui rb-stack" aria-labelledby="account-heading">
  <h1 id="account-heading" class="rb-heading rb-heading--h1">My account</h1>
  @include('account._tabs')
  <x-ui.card>@include('account._photo')</x-ui.card>
  @include('account._email-state')
  <x-ui.card class="rb-stack">@include('account._information-form')</x-ui.card>
</section>
@endsection
