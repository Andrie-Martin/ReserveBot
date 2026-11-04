<nav class="rb-account-tabs" aria-label="My account">
  <a href="{{ route('account') }}" @if(request()->routeIs('account')) aria-current="page" @endif>Information</a>
  <a href="{{ route('password.edit') }}" @if(request()->routeIs('password.edit')) aria-current="page" @endif>Password</a>
</nav>
@if (session('status'))
  <x-ui.alert tone="success" role="status">{{ session('status') }}</x-ui.alert>
@endif
@if (session('problem'))
  <x-ui.alert tone="danger" role="alert">{{ session('problem') }}</x-ui.alert>
@endif
