{{-- Only shown when APP_DEBUG=false. With APP_DEBUG=true, Laravel shows the error details instead. --}}
@include('errors.message', [
    'heading' => 'Something went wrong',
    'text' => 'Please try again. If it keeps happening, check storage/logs/laravel.log.',
    'hideUser' => true,
])
