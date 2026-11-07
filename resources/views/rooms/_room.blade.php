@use('App\Support\TimeSlots')
        @php $room = $row['room']; @endphp
        <li @class(['rb-availability__room-row', 'rb-availability__is-match' => $row['match'] === true, 'rb-availability__is-clash' => $row['match'] === false])>
          <div class="rb-availability__room-name">
            <strong>{{ $room->name }}</strong>
            <span>{{ $room->capacity ? $room->capacity.' seats' : 'Seats not recorded' }}</span>

          </div>
          <div class="rb-availability__room-body">
            @if ($row['match'] === true)
              <p class="rb-availability__matchline">✓ Free {{ TimeSlots::span($search['from'], $search['to']) }}</p>
            @elseif ($row['clash'] && ! $row['allDay'])
              <p class="rb-availability__clashline">Busy then: {{ $row['clash']['label'] }} ({{ TimeSlots::span($row['clash']['start'], $row['clash']['end']) }})</p>
            @endif

            @if ($axisValid ?? false)
              <x-ui.availability-track :ranges="$row['free']" :start="$axisStart" :end="$axisEnd" />
            @endif
            <div class="rb-availability__room-free">
              @if ($row['allDay'])
                <span class="rb-availability__none">Blocked all day: {{ $row['allDay'] }}</span>
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
              <details class="rb-availability__room-busy">
                <summary>What’s on ({{ count($row['busy']) }})</summary>
                <ul>
                  @foreach ($row['busy'] as $it)
                    <li @class(['rb-availability__mine' => $it['mine']])>
                      <span class="rb-availability__when">{{ TimeSlots::span($it['start'], $it['end']) }}</span>
                      <span class="rb-availability__what">
                        {{ $it['label'] }}
                        @if ($it['status'])
                          <span @class(['rb-availability__st', 'rb-availability__st-pending' => $it['status'] === 'Pending'])>{{ $statusWords($it) }}</span>
                        @endif
                        @foreach ($it['detail'] as $line)
                          @if ($line !== $it['label'])<span class="rb-availability__extra">{{ $line }}</span>@endif
                        @endforeach
                      </span>
                    </li>
                  @endforeach
                </ul>
              </details>
            @endif
          </div>
        </li>
