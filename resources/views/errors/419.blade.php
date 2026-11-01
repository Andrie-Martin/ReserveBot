{{-- Shown when a form was left open too long (its security token expired) --}}
@include('errors.message', [
    'heading' => 'Page expired',
    'text' => 'This page was open too long. Go back, refresh the page, and try again.',
])
