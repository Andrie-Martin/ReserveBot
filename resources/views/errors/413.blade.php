{{-- Phase 9: a file bigger than the server takes (e.g. a huge photo). Shown before the login is read, so no user menu. --}}
@include('errors.message', [
    'heading' => 'File too big',
    'text' => 'That file is too big to upload. Go back and pick a photo under 5 MB.',
    'hideUser' => true,
])
