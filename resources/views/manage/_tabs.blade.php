{{-- The Manage pages --}}
<nav class="manage-tabs" aria-label="Manage">
  <a href="{{ route('manage.rooms') }}" @class(['on' => request()->routeIs('manage.rooms*')]) @if (request()->routeIs('manage.rooms*')) aria-current="page" @endif>Rooms</a>
  <a href="{{ route('manage.equipment') }}" @class(['on' => request()->routeIs('manage.equipment*', 'manage.categories*')]) @if (request()->routeIs('manage.equipment*', 'manage.categories*')) aria-current="page" @endif>Equipment</a>
  <a href="{{ route('manage.rules') }}" @class(['on' => request()->routeIs('manage.rules*', 'manage.terms*')]) @if (request()->routeIs('manage.rules*', 'manage.terms*')) aria-current="page" @endif>Booking rules</a>
  <a href="{{ route('manage.timetable') }}" @class(['on' => request()->routeIs('manage.timetable*')]) @if (request()->routeIs('manage.timetable*')) aria-current="page" @endif>Timetable</a>
</nav>
@if (session('status'))
  <p class="success" role="status">{{ session('status') }}</p>
@endif
