<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') · ReserveBot</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="{{ url('/') }}">ReserveBot</a>
    {{-- Error pages for server problems pass hideUser, so the page never needs the database --}}
    @if (empty($hideUser))
      @auth
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
  <main>
    @yield('content')
  </main>
</body>
</html>
