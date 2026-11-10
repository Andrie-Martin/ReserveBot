<div class="rb-account" id="rb-account-controls">
  @if ($settingUp)
    <span class="rb-account__name">{{ $me->displayName() }}</span>
  @else
    <a href="{{ route('account') }}" class="rb-account__link" @if (request()->routeIs('account', 'password.edit')) aria-current="page" @endif title="My account">
      <x-avatar :user="$me" size="md" />
      <span class="rb-account__identity"><span class="rb-account__name" title="{{ $me->displayName() }}">{{ $me->displayName() }}</span><span class="rb-account__role">{{ $me->roleLabel() }}</span></span>
    </a>
  @endif
  <form method="post" action="{{ route('logout') }}">
    @csrf
    <x-ui.button type="submit" variant="secondary">Log out</x-ui.button>
  </form>
</div>
