<aside class="rb-sidebar rb-ui" id="rb-navigation">
  <nav class="rb-navigation" aria-label="Main">
    <span class="rb-navigation__heading">Workspace</span>
    <ul class="rb-navigation__list">
      @foreach ($navigation as $item)
        <li><x-ui.nav-item :href="$item['href']" :label="$item['label']" :mobile="$item['mobile'] ?? null"
            :tile="$item['tile']" :active="$item['active']" :count="$item['count'] ?? 0" /></li>
      @endforeach
    </ul>
  </nav>
  @include('layouts.partials.workspace-note')
</aside>
