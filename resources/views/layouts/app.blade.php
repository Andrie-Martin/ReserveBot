<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') · ReserveBot</title>
  {{-- ?v= changes whenever style.css changes, so browsers never keep an old copy --}}
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="{{ url('/') }}">ReserveBot</a>
    {{-- Error pages for server problems pass hideUser, so the page never needs the database --}}
    @if (empty($hideUser))
      @auth
        <nav class="topnav" aria-label="Main">
          <a href="{{ url('/') }}" @class(['active' => request()->routeIs('rep.home', 'staff.home', 'admin.home')])>Home</a>
          <a href="{{ route('rooms') }}" @class(['active' => request()->routeIs('rooms')])>Free rooms</a>
        </nav>
        <div class="who">
          <span>{{ auth()->user()->name }} · {{ auth()->user()->roleLabel() }}</span>
          <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link">Log out</button>
          </form>
        </div>
      @endauth
    @endif
  </header>
  <main class="@yield('main-class')">
    @yield('content')
  </main>
</body>
</html>
