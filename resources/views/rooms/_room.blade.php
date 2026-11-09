@use('App\Support\TimeSlots')
@use('App\Support\RoomCapacity')
        @php $room = $row['room']; @endphp
        <li id="room-{{ $room->id }}" @class(['rb-availability__room-row', 'rb-availability__is-match' => $row['match'] === true, 'rb-availability__is-clash' => $row['match'] === false])>
          <div class="rb-availability__room-name">
            <strong>{{ $room->name }}</strong>
            @if (RoomCapacity::known($room->capacity))
              <span>{{ RoomCapacity::label($room->capacity) }}</span>
            @endif
          </div>
          <div class="rb-availability__room-body">
            @if ($row['match'] === true)
              <p class="rb-availability__matchline">✓ Free {{ TimeSlots::span($search['from'], $search['to']) }}</p>
            @elseif ($row['clash'] && ! $row['allDay'])
              <p class="rb-availability__clashline">Busy then: {{ $row['clash']['label'] }} ({{ TimeSlots::span($row['clash']['start'], $row['clash']['end']) }})</p>
            @endif

            @if ($axisValid ?? false)
              <x-ui.availability-track :ranges="$row['free']" :start="$axisStart" :end="$axisEnd" :blocked="(bool) $row['allDay']" />
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
              <x-ui.link-button variant="secondary" size="compact" class="rb-availability__btn-schedule" :href="'#schedule-'.$room->id" data-rb-drawer="schedule-{{ $room->id }}">View schedule <span class="rb-availability__count">{{ count($row['busy']) }}</span><span class="sr-only"> for {{ $room->name }}, {{ count($row['busy']) }} {{ count($row['busy']) === 1 ? 'entry' : 'entries' }}</span></x-ui.link-button>
            @endif
          </div>

        @if ($row['busy'] && ! $row['allDay'])
          <x-ui.drawer :id="'schedule-'.$room->id" kicker="Room schedule" :title="$room->name" :sub="$date->format('l, F j').' · '.count($row['busy']).' scheduled'.(RoomCapacity::known($room->capacity) ? ' · '.RoomCapacity::label($room->capacity) : '')" :close-to="'#room-'.$room->id" class="rb-availability__schedule">
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
            @if (is_array($row['free']) && $row['free'])
              <h3 class="rb-availability__schedule-sub">Free times</h3>
              <p class="rb-availability__schedule-free">
                @foreach ($row['free'] as $range)
                  @if (is_array($range) && count($range) === 2 && isset($range[0], $range[1]) && is_int($range[0]) && is_int($range[1]))<span class="rb-availability__free-chip">{{ TimeSlots::span($range[0], $range[1]) }}</span>@endif
                @endforeach
              </p>
            @endif
            @if ($row['bookUrl'])
              <p class="rb-availability__schedule-actions"><x-ui.link-button :href="$row['bookUrl']">Book<span class="sr-only"> {{ $room->name }}</span></x-ui.link-button></p>
            @endif
          </x-ui.drawer>
        @endif
        </li>
