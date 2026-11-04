@extends('layouts.app')

@section('title', $ok ? 'Email connected' : 'Link not working')
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<x-ui.auth-card>
  <x-slot:heading><h1 class="rb-heading rb-heading--h1">{{ $ok ? 'Email connected' : 'This link isn’t working' }}</h1></x-slot:heading>
  <x-ui.alert :tone="$ok ? 'success' : 'danger'" role="status">{{ $message }}</x-ui.alert>
  <x-slot:footer>
    @auth
      <a href="{{ route('account') }}">Go to My account</a>
    @else
      <a href="{{ route('login') }}">Log in</a>
    @endauth
  </x-slot:footer>
</x-ui.auth-card>
@endsection
