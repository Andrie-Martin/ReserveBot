@use('App\Support\TimeSlots')
@use('App\Support\RoomCapacity')
        @php $room = $row['room']; @endphp
        <li id="room-{{ $room->id }}" @class(['rb-availability__room-row', 'rb-availability__is-match' => $row['match'] === true, 'rb-availability__is-clash' => $row['match'] === false])>
          <div class="rb-availability__room-name">
            <strong>{{ $room->name }}</strong>
            @if (RoomCapacity::known($room->capacity))
              <span>{{ RoomCapacity::label($room->capacity) }}</span>
            @endif
            @if ($row['allDay'])
              <span class="rb-availability__state rb-availability__state--blocked">Unavailable</span>
            @elseif (is_array($row['free']) && ! $row['free'])
              <span class="rb-availability__state rb-availability__state--none">No free time</span>
            @elseif (is_array($row['free']))
              <span class="rb-availability__state rb-availability__state--free">{{ count($row['free']) }} free {{ count($row['free']) === 1 ? 'window' : 'windows' }}</span>
            @endif
          </div>
          <div class="rb-availability__room-body">
            @if ($row['match'] === true)
              <p class="rb-availability__matchline">✓ Free {{ TimeSlots::span($search['from'], $search['to']) }}</p>
            @elseif ($row['clash'] && ! $row['allDay'])
              <p class="rb-availability__clashline">Busy then: {{ $row['clash']['label'] }} ({{ TimeSlots::span($row['clash']['start'], $row['clash']['end']) }})</p>
            @endif

            @if ($axisValid ?? false)
              <x-ui.availability-track :ranges="$row['free']" :start="$axisStart" :end="$axisEnd" :blocked="(bool) $row['allDay']" :ticks="$tickPercents ?? []" />
              @if (! empty($scaleLabels))
                <div class="rb-availability__scale" aria-hidden="true">@foreach ($scaleLabels as $label)<span>{{ $label }}</span>@endforeach</div>
              @endif
            @endif
            <div class="rb-availability__room-free">
              @if ($row['allDay'])
                <span class="rb-availability__none rb-availability__blocked">Unavailable all day — {{ $row['allDay'] }}</span>
              @elseif (! $row['free'])
                <span class="rb-availability__none">No free time {{ $date->isToday() ? 'left today' : 'this day' }}</span>
              @elseif (! is_array($row['free']))
                <span class="rb-availability__none">Availability time unavailable.</span>
              @else
                <span class="sr-only">Free</span>
                @foreach (is_array($row['free']) ? $row['free'] : [] as $range)
                  <span class="rb-availability__free-chip">{{ is_array($range) && count($range) === 2 && isset($range[0], $range[1]) && is_int($range[0]) && is_int($range[1]) ? TimeSlots::span($range[0], $range[1]) : 'Availability time unavailable.' }}</span>
                @endforeach
              @endif
            </div>

          </div>
          <div class="rb-availability__room-actions">
          <div class="rb-availability__room-book">
            @if ($row['bookUrl'])
              <x-ui.link-button variant="secondary" class="rb-availability__btn-book" :href="$row['bookUrl']">Book<span class="sr-only"> {{ $room->name }}</span></x-ui.link-button>
            @endif
          </div>
            @if ($row['busy'] && ! $row['allDay'])
              {{-- Visible label "Schedule" + count; the accessible name reads "View schedule for CEA300, 3 entries". --}}
              <x-ui.link-button variant="secondary" class="rb-availability__btn-schedule" :href="'#schedule-'.$room->id" data-rb-drawer="schedule-{{ $room->id }}"><svg class="rb-availability__ico" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="2.25" y="3.25" width="11.5" height="10.5" rx="1.5"/><path d="M2.25 6.75h11.5M5.5 2v2.5M10.5 2v2.5"/></svg><span class="sr-only">View </span>Schedule<span class="rb-availability__count" aria-hidden="true">{{ count($row['busy']) }}</span><span class="sr-only"> for {{ $room->name }}, {{ count($row['busy']) }} {{ count($row['busy']) === 1 ? 'entry' : 'entries' }}</span></x-ui.link-button>
            @endif
          </div>

        @if ($row['busy'] && ! $row['allDay'])
          <x-ui.drawer :id="'schedule-'.$room->id" kicker="Room schedule" :title="$room->name" :sub="$date->format('l, F j').' · '.count($row['busy']).' scheduled'.(RoomCapacity::known($room->capacity) ? ' · '.RoomCapacity::label($room->capacity) : '')" :close-to="'#room-'.$room->id" class="rb-availability__schedule">
            <section class="rb-availability__schedule-section" aria-labelledby="schedule-{{ $room->id }}-events">
            <h3 class="rb-availability__schedule-sub" id="schedule-{{ $room->id }}-events">Scheduled <span class="rb-availability__count">{{ count($row['busy']) }}</span></h3>
            <ul class="rb-availability__schedule-list">
              @foreach ($row['busy'] as $it)
                <li @class(['rb-availability__mine' => $it['mine']])>
                  <p class="rb-availability__when">{{ TimeSlots::span($it['start'], $it['end']) }}</p>
                  <p class="rb-availability__what">{{ $it['label'] }}
                    @if ($it['status'])
                      <span @class(['rb-availability__st', 'rb-availability__st-pending' => $it['status'] === 'Pending'])>{{ $statusWords($it) }}</span>
                    @endif
                  </p>
                  @foreach ($it['detail'] as $line)
                    @if ($line !== $it['label'])<p class="rb-availability__extra">{{ $line }}</p>@endif
                  @endforeach
                </li>
              @endforeach
            </ul>
            </section>
            @if (is_array($row['free']))
              {{-- Divider = the neutral border token; an empty free list says so instead of vanishing. --}}
              <section class="rb-availability__schedule-section rb-availability__schedule-section--free" aria-labelledby="schedule-{{ $room->id }}-free">
              <h3 class="rb-availability__schedule-sub" id="schedule-{{ $room->id }}-free">Free times</h3>
              @if ($row['free'])
                <p class="rb-availability__schedule-free">
                  @foreach ($row['free'] as $range)
                    @if (is_array($range) && count($range) === 2 && isset($range[0], $range[1]) && is_int($range[0]) && is_int($range[1]))<span class="rb-availability__free-chip">{{ TimeSlots::span($range[0], $range[1]) }}</span>@endif
                  @endforeach
                </p>
              @else
                <p class="rb-availability__schedule-free rb-availability__none">No free time {{ $date->isToday() ? 'left today' : 'this day' }}</p>
              @endif
              </section>
            @endif
            @if ($row['bookUrl'])
              <p class="rb-availability__schedule-actions"><x-ui.link-button :href="$row['bookUrl']">Book<span class="sr-only"> {{ $room->name }}</span></x-ui.link-button></p>
            @endif
          </x-ui.drawer>
        @endif
        </li>
