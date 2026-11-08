{{-- A request the server refused as not valid (e.g. an answer button whose value was changed). Without this view Laravel shows its
     plain unstyled page, which ignores the browser's text size. --}}
@include('errors.message', [
    'heading' => 'Request not accepted',
    'text' => 'That request couldn’t be used. Go back, refresh the page, and try again.',
])
