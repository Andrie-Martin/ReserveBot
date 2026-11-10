{{-- "Set up your department": shown on the Admin's and lab staff's home page while the department is missing something
     it needs before its blocks can book (App\Services\DepartmentSetup). A department the College admin just added
     starts empty, so this is how its Admin is asked for the rooms, timetable, equipment and accounts. Needs $setup and $user. --}}
@php
  $neededTotal = collect($setup['steps'])->where('needed', true)->count();
  $neededDone = $neededTotal - count($setup['left']);
@endphp
<x-ui.card class="rb-dashboard__setup" id="setup" aria-labelledby="setup-title">
  <h2 id="setup-title">Set up {{ $user->department->name }}</h2>
  <p class="rb-setup__intro">This department doesn’t have everything it needs yet. Add the things below: its blocks can start booking once the steps marked <strong>Needed</strong> are done ({{ $neededDone }} of {{ $neededTotal }} so far).</p>
  <ol class="rb-setup">
    @foreach ($setup['steps'] as $step)
      <li @class(['rb-setup__step', 'is-done' => $step['done']]) id="setup-{{ $step['key'] }}">
        <span class="rb-setup__state">
          @if ($step['done'])
            <x-ui.badge tone="success" label="Done" />
          @elseif ($step['needed'])
            <x-ui.badge tone="warning" label="Needed" />
          @else
            <x-ui.badge tone="neutral" label="Optional" />
          @endif
        </span>
        <div class="rb-setup__what">
          <p class="rb-setup__title">{{ $step['title'] }}@if ($step['done']) <span class="rb-setup__count">({{ $step['count'] }} so far)</span>@endif</p>
          <p class="rb-setup__text">{{ $step['text'] }}</p>
        </div>
        @if ($step['url'])
          <x-ui.link-button variant="secondary" size="compact" class="rb-setup__link" :href="$step['url']">{{ $step['done'] ? 'Open' : $step['link'] }}<span class="sr-only">: {{ $step['title'] }}</span></x-ui.link-button>
        @else
          <span class="rb-setup__who">Your department’s Admin does this</span>
        @endif
      </li>
    @endforeach
  </ol>
  <p class="rb-setup__note">Booking rules are already set to the usual ones (open Monday to Saturday, 7:30 AM to 9:00 PM, requests need approval). Change them any time on Manage → Booking rules.</p>
</x-ui.card>
