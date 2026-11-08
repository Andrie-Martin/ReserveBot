@extends('layouts.app')

@section('title', $heading)
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
<x-ui.auth-card class="rb-auth-card--message">
  <x-slot:heading><h1 class="rb-heading rb-heading--h1">{{ $heading }}</h1></x-slot:heading>
  <p class="rb-description">{{ $text }}</p>
  <x-slot:footer><a href="{{ url('/') }}">Go to my home page</a></x-slot:footer>
</x-ui.auth-card>
@endsection
