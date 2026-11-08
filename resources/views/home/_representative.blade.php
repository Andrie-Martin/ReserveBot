@use('App\Services\ClassChecks')
@use('App\Support\TimeSlots')
<div class="rb-dashboard rb-ui">
<header class="rb-dashboard__intro">
  <p class="rb-dashboard__kicker">{{ $user->roleLabel() }}</p>
  <h1>Welcome, {{ $user->name }}</h1>
  <p class="muted">
    {{ $user->department->name }}@if ($user->section) · {{ $user->section->name }}@endif
  </p>

  @if ($stat)
    <p class="rb-stat"><strong class="rb-stat__value">{{ $stat['value'] }}</strong> <span class="rb-stat__label">{{ $stat['label'] }}</span></p>
  @endif

</header>
<div class="rb-dashboard__banner">
  <p class="rb-dashboard__banner-kicker">Your workspace</p>
  <p class="rb-dashboard__banner-text">{{ $intro }}</p>
</div>
  @if (! empty($ready))
    <x-ui.card class="rb-dashboard__shortcuts">
    <h2>Ready to use</h2>
    <div class="rb-dashboard__links">
      @foreach ($ready as $link)
        @php $icon = $link['url'] === route('book') ? 'book' : ($link['url'] === route('bookings.mine') ? 'bookings' : 'rooms'); @endphp
        <x-ui.link-button variant="secondary" :href="$link['url']" class="rb-dashboard__action">
          <span class="rb-dashboard__action-icon"><x-ui.icon :name="$icon" /></span>
          <span class="rb-dashboard__action-label">{{ $link['label'] }}</span> <span class="rb-dashboard__action-arrow" aria-hidden="true">→</span>
        </x-ui.link-button>
      @endforeach
    </div>
    </x-ui.card>
  @endif

<div class="rb-dashboard__grid">
<div class="rb-dashboard__schedule">
  @if (! empty($meetings))
    {{-- Phase 10: is each of the block's classes face-to-face? Online frees the room for others. --}}
    <x-ui.card class="rb-dashboard__meetings">
    <h2 id="face-to-face">Face-to-face this week?</h2>
    @if (session('status'))
      <p class="success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('problem'))
      <p class="error" role="alert">{{ session('problem') }}</p>
    @endif
    <p class="hint meet-hint">We email you a day, 5 hours and 1 hour before each class. <strong>Online</strong> frees the room for others, and so does no answer by the 5-hour email.</p>
    @php
      // Presentation grouping only; classes._answer remains the authority for controls.
      [$attention, $otherMeetings] = collect($meetings)->sortBy(fn ($meeting) => $meeting['start']->getTimestamp())
        ->partition(fn ($meeting) => $meeting['start']->isToday()
          || (! $meeting['end']->isPast() && in_array($meeting['state'], ['waiting', 'freed'], true)));
    @endphp
    @if ($attention->isNotEmpty())
      <h3>Unanswered and today's meetings ({{ $attention->count() }})</h3>
      <ul class="mlist meet-list" data-meetings="attention">
        @foreach ($attention as $m)
          @include('home._meeting')
        @endforeach
      </ul>
    @endif
    @if ($otherMeetings->isNotEmpty())
      <div class="rb-dashboard__preview" data-meetings="other">
        <h3>Next up ({{ $otherMeetings->count() }} more)</h3>
        <ul class="mlist meet-list" data-meetings="preview">
          @foreach ($otherMeetings->take(3) as $m)
            @include('home._meeting')
          @endforeach
        </ul>
        <p class="rb-dashboard__more">
          <x-ui.link-button variant="secondary" size="compact" href="#meetings-all" data-rb-drawer="meetings-all">View all {{ $meetings ? count($meetings) : 0 }} meetings</x-ui.link-button>
        </p>
      </div>
      <x-ui.drawer id="meetings-all" kicker="Face-to-face this week?" title="All upcoming meetings" sub="Your block's classes in the next 7 days, soonest first." close-to="#face-to-face">
        <ul class="mlist meet-list rb-dashboard__meetings" data-meetings="all">
          @foreach (collect($meetings)->sortBy(fn ($meeting) => $meeting['start']->getTimestamp()) as $m)
            @include('home._meeting')
          @endforeach
        </ul>
      </x-ui.drawer>
    @endif
    </x-ui.card>
  @endif

  @if ($classes !== null)
    <x-ui.card class="rb-dashboard__classes">
    <h2 id="timetable-heading">Your block's classes this semester</h2>
    @if ($classes->isEmpty())
      <p class="muted">No classes found for your block this semester.</p>
    @else
      @php
        $isoDay = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
        $clockNow = now();
        $nextClasses = $classes->map(function ($class) use ($isoDay, $clockNow) {
            $offset = (($isoDay[$class->day_of_week] ?? 1) - $clockNow->dayOfWeekIso + 7) % 7;
            if ($clockNow->copy()->startOfDay()->addDays($offset)->setTimeFromTimeString($class->end_time)->lte($clockNow)) $offset += 7;
            return ['class' => $class, 'offset' => $offset];
        })->sort(fn ($a, $b) => [$a['offset'], $a['class']->start_time] <=> [$b['offset'], $b['class']->start_time])->take(4);
      @endphp
      <div class="rb-dashboard__preview">
        <h3>Next classes</h3>
        <ul class="rb-dashboard__next">
          @foreach ($nextClasses as $next)
            @php $class = $next['class']; @endphp
            <li>
              <span class="rb-dashboard__next-when">{{ $next['offset'] === 0 ? 'Today' : ($next['offset'] === 1 ? 'Tomorrow' : $class->day_of_week) }} · {{ $class->time_range }}</span>
              <span class="rb-dashboard__next-what">{{ $class->course->code }}@if ($class->course->title) · {{ $class->course->title }}@endif</span>
              <span class="rb-dashboard__next-where">{{ $class->room->name }}@if ($class->instructor) · {{ $class->instructor }}@endif</span>
            </li>
          @endforeach
        </ul>
        <p class="rb-dashboard__more">
          <x-ui.link-button variant="secondary" size="compact" href="#timetable-all" data-rb-drawer="timetable-all">View full timetable ({{ $classes->count() }} classes)</x-ui.link-button>
        </p>
      </div>
      <x-ui.drawer id="timetable-all" kicker="This semester" title="Your block's timetable" :sub="$classes->count().' classes this semester.'" close-to="#timetable-heading" class="rb-dashboard__timetable-drawer">
      <x-ui.table-container label="Your block’s semester class schedule">
      <table>
        <thead>
          <tr><th scope="col">Day</th><th scope="col">Time</th><th scope="col">Room</th><th scope="col">Course</th><th scope="col">Instructor</th></tr>
        </thead>
        <tbody>
          @foreach ($classes as $class)
            <tr>
              <td>{{ $class->day_of_week }}</td>
              <td>{{ $class->time_range }}</td>
              <td>{{ $class->room->name }}</td>
              <td>{{ $class->course->code }}@if ($class->course->title) · {{ $class->course->title }}@endif</td>
              <td>{{ $class->instructor }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      </x-ui.table-container>
      </x-ui.drawer>
    @endif

    </x-ui.card>
  @endif

</div>
</div>
</div>
