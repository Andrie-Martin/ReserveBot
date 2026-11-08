{{-- The Manage pages --}}
<nav class="rb-subnav" aria-label="Manage">
  <a class="rb-subnav__link" href="{{ route('manage.rooms') }}" @if (request()->routeIs('manage.rooms*')) aria-current="page" @endif>Rooms</a>
  <a class="rb-subnav__link" href="{{ route('manage.equipment') }}" @if (request()->routeIs('manage.equipment*', 'manage.categories*')) aria-current="page" @endif>Equipment</a>
  <a class="rb-subnav__link" href="{{ route('manage.rules') }}" @if (request()->routeIs('manage.rules*', 'manage.terms*')) aria-current="page" @endif>Booking rules</a>
  <a class="rb-subnav__link" href="{{ route('manage.timetable') }}" @if (request()->routeIs('manage.timetable*')) aria-current="page" @endif>Timetable</a>
</nav>
@if (session('status'))
  <p class="success" role="status">{{ session('status') }}</p>
@endif
