@use('App\Models\Reservation')
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
          @if (auth()->user()->role === 'rep')
            <a href="{{ route('book') }}" @class(['active' => request()->routeIs('book')])>Book</a>
            <a href="{{ route('bookings.mine') }}" @class(['active' => request()->routeIs('bookings.mine', 'bookings.show')])>My bookings</a>
          @else
            {{-- Lab staff and admins: how many requests wait for approval --}}
            @php $waitingCount = Reservation::waitingCountFor(auth()->user()); @endphp
            <a href="{{ route('requests') }}" @class(['active' => request()->routeIs('requests', 'bookings.show')])>Requests @if ($waitingCount)<span class="nav-count">{{ $waitingCount }}<span class="sr-only"> waiting</span></span>@endif</a>
          @endif
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
