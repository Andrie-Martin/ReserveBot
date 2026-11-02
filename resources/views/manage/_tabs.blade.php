{{-- The three Manage pages --}}
<nav class="manage-tabs" aria-label="Manage">
  <a href="{{ route('manage.rooms') }}" @class(['on' => request()->routeIs('manage.rooms*')])>Rooms</a>
  <a href="{{ route('manage.equipment') }}" @class(['on' => request()->routeIs('manage.equipment*', 'manage.categories*')])>Equipment</a>
  <a href="{{ route('manage.rules') }}" @class(['on' => request()->routeIs('manage.rules*', 'manage.terms*')])>Booking rules</a>
</nav>
@if (session('status'))
  <p class="success" role="status">{{ session('status') }}</p>
@endif
