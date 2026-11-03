@use('App\Models\Reservation')
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') · ReserveBot</title>
  {{-- ?v= changes whenever style.css changes, so browsers never keep an old copy --}}
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/fonts.css') }}?v={{ filemtime(public_path('css/foundations/fonts.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/tokens.css') }}?v={{ filemtime(public_path('css/foundations/tokens.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/components.css') }}?v={{ filemtime(public_path('css/foundations/components.css')) }}">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="{{ url('/') }}">ReserveBot</a>
    {{-- Error pages for server problems pass hideUser, so the page never needs the database --}}
    @if (empty($hideUser))
      @auth
        @php
          $me = auth()->user();
          $settingUp = $me->must_change_password;     // first login: only the "pick your password" page
        @endphp
        @unless ($settingUp)
          <nav class="topnav" aria-label="Main">
            <a href="{{ url('/') }}" @class(['active' => request()->routeIs('rep.home', 'staff.home', 'admin.home')])>Home</a>
            <a href="{{ route('rooms') }}" @class(['active' => request()->routeIs('rooms')])>Free rooms</a>
            @if ($me->role === 'rep')
              <a href="{{ route('book') }}" @class(['active' => request()->routeIs('book')])>Book</a>
              <a href="{{ route('bookings.mine') }}" @class(['active' => request()->routeIs('bookings.mine', 'bookings.show')])>My bookings</a>
            @else
              {{-- Lab staff and admins: how many requests wait for approval --}}
              @php $waitingCount = Reservation::waitingCountFor($me); @endphp
              <a href="{{ route('today') }}" @class(['active' => request()->routeIs('today')])>Today</a>
              <a href="{{ route('requests') }}" @class(['active' => request()->routeIs('requests')])>Requests @if ($waitingCount)<span class="nav-count">{{ $waitingCount }}<span class="sr-only"> waiting</span></span>@endif</a>
              <a href="{{ route('manage.rooms') }}" @class(['active' => request()->routeIs('manage.*')])>Manage</a>
              @if ($me->role === 'admin')
                <a href="{{ route('accounts.index') }}" @class(['active' => request()->routeIs('accounts.*')])>Accounts</a>
              @endif
            @endif
          </nav>
        @endunless
        <div class="who">
          @if ($settingUp)
            <span>{{ $me->name }}</span>
          @else
            {{-- Name and photo open My account (Information and Password) --}}
            <a href="{{ route('account') }}" @class(['who-me', 'active' => request()->routeIs('account', 'password.edit')]) title="My account">
              <x-avatar :user="$me" />
              <span class="who-name">{{ $me->name }}</span><span class="who-role"> · {{ $me->roleLabel() }}</span>
            </a>
          @endif
          <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link">Log out</button>
          </form>
        </div>
      @endauth
    @endif
  </header>
  @if (empty($hideUser))
    @auth
      {{-- Phase 9: until the emailed link is clicked (reps can't book before that) --}}
      @if (! auth()->user()->must_change_password && ! auth()->user()->hasConnectedEmail() && ! request()->routeIs('account'))
        <div class="connect-bar" role="status">
          @if (session('connect_note'))
            {{-- The answer to "Send it again" --}}
            <p>{{ session('connect_note') }}</p>
          @else
            <p><strong>Connect your email:</strong> open the email we sent to {{ auth()->user()->email }} and click “Connect my email”{{ auth()->user()->role === 'rep' ? ' to start booking' : '' }}.</p>
            <form method="post" action="{{ route('email.resend') }}">
              @csrf
              <button type="submit">Send it again</button>
            </form>
          @endif
        </div>
      @endif
    @endauth
  @endif
  <main class="@yield('main-class')">
    @yield('content')
  </main>
</body>
</html>
