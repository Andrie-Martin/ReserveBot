@use('App\Services\ClassChecks')
@use('App\Support\TimeSlots')
<div class="rb-dashboard rb-ui">
<header class="rb-dashboard__intro">
  <h1>Welcome, {{ $user->name }}</h1>
  <p class="muted">
    {{ $user->department->name }}@if ($user->section) · {{ $user->section->name }}@endif
  </p>
  <p>{{ $intro }}</p>

  @if ($stat)
    <p class="stat"><strong>{{ $stat['value'] }}</strong> {{ $stat['label'] }}</p>
  @endif

</header>
  @if (! empty($ready))
    <x-ui.card class="rb-dashboard__shortcuts">
    <h2>Ready to use</h2>
    <div class="rb-dashboard__links">
      @foreach ($ready as $link)
        <x-ui.link-button variant="secondary" :href="$link['url']">{{ $link['label'] }} →</x-ui.link-button>
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
      <x-ui.disclosure :summary="'Other upcoming meetings ('.$otherMeetings->count().')'" class="rb-dashboard__other">
        <ul class="mlist meet-list" data-meetings="other">
          @foreach ($otherMeetings as $m)
            @include('home._meeting')
          @endforeach
        </ul>
      </x-ui.disclosure>
    @endif
    </x-ui.card>
  @endif

  @if ($classes !== null)
    <x-ui.card class="rb-dashboard__classes">
    <h2>Your block's classes this semester</h2>
    <x-ui.disclosure :summary="'Complete timetable ('.$classes->count().' classes)'" class="rb-dashboard__timetable">
    @if ($classes->isEmpty())
      <p class="muted">No classes found for your block this semester.</p>
    @else
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
    @endif
    </x-ui.disclosure>
    </x-ui.card>
  @endif

</div>
  @if (! empty($comingNext))
    <h2>Coming in the next phases</h2>
    <ul>
      @foreach ($comingNext as $item)
        <li>{{ $item }}</li>
      @endforeach
    </ul>
  @endif
</div>
</div>
