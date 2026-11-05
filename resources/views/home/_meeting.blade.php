@use('App\Services\ClassChecks')
@use('App\Support\TimeSlots')
        @php $freed = in_array($m['state'], ['online', 'freed'], true); @endphp
        <li @class(['freed' => $freed])>
          <span class="mlist-when">{{ $m['start']->isToday() ? 'Today' : ($m['start']->isTomorrow() ? 'Tomorrow' : $m['start']->format('D, M j')) }} · {{ TimeSlots::span($m['start']->hour * 60 + $m['start']->minute, $m['end']->hour * 60 + $m['end']->minute) }}</span>
          <span class="mlist-what">{{ $m['class']->course?->code ?? 'Class' }} · {{ $m['class']->room->name }}</span>
          <span class="st {{ ClassChecks::BADGES[$m['state']] }}">{{ ClassChecks::LABELS[$m['state']] }}</span>
          @include('classes._answer', ['m' => $m, 'route' => 'classes.answer'])
        </li>
