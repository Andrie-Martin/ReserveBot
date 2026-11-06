<aside class="rb-sidebar rb-ui" id="rb-navigation">
  <div class="rb-role-card">
    <span class="rb-role-card__label">Signed in as</span>
    <strong class="rb-role-card__role">{{ $me->roleLabel() }}</strong>
  </div>
  <nav class="rb-navigation" aria-label="Main">
    <span class="rb-navigation__heading">Workspace</span>
    <ul class="rb-navigation__list">
      @foreach ($navigation as $item)
        <li><x-ui.nav-item :href="$item['href']" :label="$item['label']" :mobile="$item['mobile'] ?? null"
            :icon="$item['icon']" :active="$item['active']" :count="$item['count'] ?? 0" /></li>
      @endforeach
    </ul>
  </nav>
  @include('layouts.partials.workspace-note')
</aside>
