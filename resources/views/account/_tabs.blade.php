{{-- Phase 9: the two My account pages, and their messages --}}
<nav class="manage-tabs" aria-label="My account">
  <a href="{{ route('account') }}" @class(['on' => request()->routeIs('account')])>Information</a>
  <a href="{{ route('password.edit') }}" @class(['on' => request()->routeIs('password.edit')])>Password</a>
</nav>
@if (session('status'))
  <p class="success" role="status">{{ session('status') }}</p>
@endif
@if (session('problem'))
  <p class="error" role="alert">{{ session('problem') }}</p>
@endif
