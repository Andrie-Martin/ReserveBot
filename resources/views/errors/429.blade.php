{{-- Phase 9: too many tries in a short time (e.g. asking for password links) --}}
@include('errors.message', [
    'heading' => 'Too many tries',
    'text' => 'Wait a minute, then go back and try again.',
])
