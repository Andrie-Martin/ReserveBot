@php
  // The one page-to-pose mapping for the sidebar mascot, keyed by nav icon so every role's links are covered. A link's pose is
  // what the mascot shows while it is hovered or focused; the ACTIVE link's pose is what the page starts with, so child pages
  // (booking detail, manage sub-pages...) inherit their parent navigation item's pose. Unmapped links get a neutral gesture;
  // a page with no active link (account, password) shows the plain idle figure.
  $poses = ['home' => 'wave', 'rooms' => 'door', 'book' => 'calendar', 'bookings' => 'clipboard', 'today' => 'calendar',
            'requests' => 'clipboard', 'manage' => 'key', 'equipment' => 'tool', 'accounts' => 'badge'];
  $poseOf = fn ($item) => $poses[$item['icon']] ?? 'attend';
  $pagePose = 'idle';
  foreach ($navigation as $item) {
      if ($item['active']) { $pagePose = $poseOf($item); break; }
  }
@endphp
<aside class="rb-sidebar rb-ui" id="rb-navigation">
  <nav class="rb-navigation" aria-label="Main">
    <span class="rb-navigation__heading">Workspace</span>
    <ul class="rb-navigation__list">
      @foreach ($navigation as $item)
        <li><x-ui.nav-item :href="$item['href']" :label="$item['label']" :mobile="$item['mobile'] ?? null"
            :icon="$item['icon']" :active="$item['active']" :count="$item['count'] ?? 0" :pose="$poseOf($item)" /></li>
      @endforeach
    </ul>
  </nav>
  <x-ui.mascot :pose="$pagePose" />
</aside>
