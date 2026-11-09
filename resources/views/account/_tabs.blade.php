<nav class="rb-subnav rb-account-tabs" aria-label="My account">
  <a class="rb-subnav__link" href="{{ route('account') }}" @if(request()->routeIs('account')) aria-current="page" @endif>Information</a>
  <a class="rb-subnav__link" href="{{ route('password.edit') }}" @if(request()->routeIs('password.edit')) aria-current="page" @endif>Password</a>
</nav>
