@use('App\Models\Reservation')
<!DOCTYPE html>
<html lang="en" class="rb-layout-document">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') · ReserveBot</title>
  {{-- ?v= changes whenever style.css changes, so browsers never keep an old copy --}}
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/fonts.css') }}?v={{ filemtime(public_path('css/foundations/fonts.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/tokens.css') }}?v={{ filemtime(public_path('css/foundations/tokens.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/components.css') }}?v={{ filemtime(public_path('css/foundations/components.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ filemtime(public_path('css/layout.css')) }}">
  @stack('styles')
  <script src="{{ asset('js/layout.js') }}?v={{ filemtime(public_path('js/layout.js')) }}" defer></script>
</head>
@php
  // Keep this guard before authentication: server/upload error pages must not resolve a user.
  $me = empty($hideUser) ? auth()->user() : null;
  $settingUp = $me && $me->must_change_password;
  $workspace = $me && ! $settingUp;
  $representative = $workspace && $me->role === 'rep';
  $navigation = [];
  if ($workspace) {
      $navigation = [
          ['label' => 'Home', 'mobile' => 'Home', 'icon' => 'home', 'href' => url('/'), 'active' => request()->routeIs('rep.home', 'staff.home', 'admin.home')],
          ['label' => 'Free rooms', 'mobile' => 'Rooms', 'icon' => 'rooms', 'href' => route('rooms'), 'active' => request()->routeIs('rooms')],
      ];
      if ($representative) {
          $navigation[] = ['label' => 'Book', 'mobile' => 'Book', 'icon' => 'book', 'href' => route('book'), 'active' => request()->routeIs('book')];
          $navigation[] = ['label' => 'My bookings', 'mobile' => 'Bookings', 'icon' => 'bookings', 'href' => route('bookings.mine'), 'active' => request()->routeIs('bookings.mine', 'bookings.show')];
      } else {
          // One department-scoped query; the same links serve desktop and mobile.
          $waitingCount = Reservation::waitingCountFor($me);
          $equipmentActive = request()->routeIs('manage.equipment*', 'manage.categories*');
          $navigation[] = ['label' => 'Today', 'icon' => 'today', 'href' => route('today'), 'active' => request()->routeIs('today')];
          $navigation[] = ['label' => 'Requests', 'icon' => 'requests', 'href' => route('requests'), 'active' => request()->routeIs('requests'), 'count' => $waitingCount];
          $navigation[] = ['label' => 'Manage rooms', 'icon' => 'manage', 'href' => route('manage.rooms'), 'active' => request()->routeIs('manage.*') && ! $equipmentActive];
          $navigation[] = ['label' => 'Equipment', 'icon' => 'equipment', 'href' => route('manage.equipment'), 'active' => $equipmentActive];
          if ($me->role === 'admin') {
              $navigation[] = ['label' => 'Accounts', 'icon' => 'accounts', 'href' => route('accounts.index'), 'active' => request()->routeIs('accounts.*')];
          }
      }
  }
@endphp
<body @class(['rb-shell', 'rb-shell--guest' => ! $me, 'rb-shell--workspace' => $workspace, 'rb-shell--rep' => $representative])>
  <a class="rb-skip-link" href="#rb-main">Skip to content</a>
  @include('layouts.partials.masthead')
  @include('layouts.partials.header')
  @if ($workspace)
    @include('layouts.partials.navigation')
  @endif
  <div class="rb-shell__content" @if ($workspace) role="region" aria-label="Page content" tabindex="0" @endif>
    @if ($workspace && ! $me->hasConnectedEmail() && ! request()->routeIs('account'))
      @include('layouts.partials.email-notice')
    @endif
    <main id="rb-main" tabindex="-1" class="rb-shell__main @yield('main-class')">
      @yield('content')
    </main>
  </div>
</body>
</html>
