@props(['name'])
{{-- Local decorative outlines. The adjacent navigation text supplies the accessible name. --}}
@php
  $paths = [
    'home' => 'M3 10 12 3l9 7M5 9v12h5v-7h4v7h5V9',
    'rooms' => 'M4 21h16M6 21V3h12v18M14 12h1',
    'book' => 'M5 5h14v16H5zM8 3v4m8-4v4M5 10h14m-7 3v5m-2.5-2.5h5',
    'bookings' => 'M8 5H5v16h14V5h-3M8 3h8v4H8zM8 12h8m-8 4h6',
    'today' => 'M5 5h14v16H5zM8 3v4m8-4v4M5 10h14m-11 5 2 2 5-4',
    'requests' => 'M4 4h16v16H4zM4 14h5l1 3h4l1-3h5M8 8h8m-8 3h5',
    'manage' => 'M4 21V3h12v6M2 21h10m0-9v9m-4-6h8m2-3 3 3-6 6h-3v-3z',
    'equipment' => 'm3 7 9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4m-9 4v10M7.5 5l9 4',
    'accounts' => 'M15 21v-2a5 5 0 0 0-10 0v2m12-7a4 4 0 0 1 4 4v3M14 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm3-3a4 4 0 0 1 0 8',
  ];
@endphp
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['rooms'] }}" /></svg>
