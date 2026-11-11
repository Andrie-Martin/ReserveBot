<header class="rb-header rb-ui">
  <x-ui.brand :href="url('/')" :animated="(bool) $workspace" />
  @if ($workspace)
    {{-- Desktop topbar only (CSS hides it in the mobile layout); the page title comes from the view's own section. --}}
    @hasSection('title')
      <p class="rb-header__crumb"><span>Workspace</span><span class="rb-header__crumb-sep" aria-hidden="true">›</span><strong>@yield('title')</strong></p>
    @endif
    <x-ui.button variant="quiet" class="rb-menu-toggle" id="rb-menu-toggle" aria-expanded="false"
        :aria-controls="$representative ? 'rb-account-controls' : 'rb-account-controls rb-navigation'">
      <span aria-hidden="true">☰</span><span class="sr-only">Account{{ $representative ? '' : ' and navigation' }} menu</span>
    </x-ui.button>
  @endif
  @if ($me)
    @include('layouts.partials.account')
  @endif
</header>
