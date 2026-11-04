<header class="rb-header rb-ui">
  <x-ui.brand :href="url('/')" />
  <span class="rb-header__description">Room &amp; equipment reservation system</span>
  @if ($workspace)
    <x-ui.button variant="quiet" class="rb-menu-toggle" id="rb-menu-toggle" aria-expanded="true"
        :aria-controls="$representative ? 'rb-account-controls' : 'rb-account-controls rb-navigation'" hidden>
      <span aria-hidden="true">☰</span><span class="sr-only">Account{{ $representative ? '' : ' and navigation' }} menu</span>
    </x-ui.button>
  @endif
  @if ($me)
    @include('layouts.partials.account')
  @endif
</header>
