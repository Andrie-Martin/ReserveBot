@use('App\Models\Reservation')
<!DOCTYPE html>
<html lang="en" class="rb-layout-document">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') · ReserveBot</title>
  {{-- Start the above-the-fold Inter weights with the document, not after the CSS is parsed, so text does not swap late. --}}
  @foreach (['Regular', 'Medium', 'SemiBold', 'Bold'] as $weight)
    <link rel="preload" href="{{ asset('fonts/inter/Inter-'.$weight.'.woff2') }}" as="font" type="font/woff2" crossorigin>
  @endforeach
  {{-- ?v= changes whenever style.css changes, so browsers never keep an old copy --}}
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/fonts.css') }}?v={{ filemtime(public_path('css/foundations/fonts.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/tokens.css') }}?v={{ filemtime(public_path('css/foundations/tokens.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/foundations/components.css') }}?v={{ filemtime(public_path('css/foundations/components.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ filemtime(public_path('css/layout.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/mascot.css') }}?v={{ filemtime(public_path('css/mascot.css')) }}">
  @stack('styles')
  <script src="{{ asset('js/layout.js') }}?v={{ filemtime(public_path('js/layout.js')) }}" defer></script>
  <script src="{{ asset('js/ui.js') }}?v={{ filemtime(public_path('js/ui.js')) }}" defer></script>
  <script src="{{ asset('js/mascot.js') }}?v={{ filemtime(public_path('js/mascot.js')) }}" defer></script>
</head>
@php
  // Keep this guard before authentication: server/upload error pages must not resolve a user.
  $me = empty($hideUser) ? auth()->user() : null;
  $settingUp = $me && $me->must_change_password;
  $workspace = $me && ! $settingUp;
  $representative = $workspace && $me->role === 'rep';
  $navigation = [];
  $collegeAdmin = $workspace && $me->isCollegeAdmin();
  if ($collegeAdmin) {
      // College-wide, with no department: only the departments and their Admin accounts
      $navigation = [
          ['label' => 'Departments', 'mobile' => 'Departments', 'icon' => 'accounts', 'href' => route('college.home'), 'active' => request()->routeIs('college.*')],
      ];
  } elseif ($workspace) {
      $navigation = [
          ['label' => 'Home', 'mobile' => 'Home', 'icon' => 'home', 'href' => url('/'), 'active' => request()->routeIs('rep.home', 'staff.home', 'admin.home')],
          ['label' => 'Free rooms', 'mobile' => 'Rooms', 'icon' => 'rooms', 'href' => route('rooms'), 'active' => request()->routeIs('rooms')],
      ];
      if ($representative) {
          $navigation[] = ['label' => 'Book', 'mobile' => 'Book', 'icon' => 'book', 'href' => route('book'), 'active' => request()->routeIs('book')];
          $navigation[] = ['label' => 'My bookings', 'mobile' => 'Bookings', 'icon' => 'bookings', 'href' => route('bookings.mine'), 'active' => request()->routeIs('bookings.mine', 'bookings.show')];
      } else {
          // A booking's page (/bookings/{id}) has no menu entry of its own; staff keep the entry they came through. Only the two
          // known origins are honoured from ?from=; otherwise a waiting request belongs to Requests and anything else to Today.
          $onBooking = request()->routeIs('bookings.show');
          $bookingFrom = request()->query('from');
          $bookingSection = $onBooking
              ? (in_array($bookingFrom, ['requests', 'today'], true) ? $bookingFrom : (request()->route('reservation')?->status === 'Pending' ? 'requests' : 'today'))
              : null;
          // One department-scoped query; the same links serve desktop and mobile.
          $waitingCount = Reservation::waitingCountFor($me);
          $equipmentActive = request()->routeIs('manage.equipment*', 'manage.categories*');
          $navigation[] = ['label' => 'Today', 'icon' => 'today', 'href' => route('today'), 'active' => request()->routeIs('today') || $bookingSection === 'today'];
          $navigation[] = ['label' => 'Requests', 'icon' => 'requests', 'href' => route('requests'), 'active' => request()->routeIs('requests') || $bookingSection === 'requests', 'count' => $waitingCount];
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
