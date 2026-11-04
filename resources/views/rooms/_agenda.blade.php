@use('App\Support\TimeSlots')
    <div class="rb-availability__dayplan">
      <h3>{{ $staffView ? 'Bookings this day' : 'Your day' }}</h3>
      @if ($dayItems)
        <ul class="rb-availability__agenda">
          @foreach ($dayItems as $it)
            <li @class(['k-'.$it['kind'], 'rb-availability__pending' => $it['status'] === 'Pending'])>
              <span class="rb-availability__when">{{ TimeSlots::span($it['start'], $it['end']) }}</span>
              <span class="rb-availability__what">{{ $it['title'] }} <span class="rb-availability__where">· {{ $it['room'] }}</span></span>
              @if ($it['tag'])
                <span @class(['rb-availability__st', 'rb-availability__st-pending' => $it['status'] === 'Pending'])>{{ $it['tag'] }}</span>
              @endif
              @if (! empty($it['check']))
                {{-- Phase 10: is the class face-to-face? --}}
                <span @class(['rb-availability__st', 'rb-availability__st-freed' => ! empty($it['freed'])])>{{ $it['check'] }}</span>
              @endif
              @if (! empty($it['bookUrl']))
                <x-ui.link-button variant="secondary" class="rb-availability__btn-book" :href="$it['bookUrl']">Book<span class="sr-only"> {{ $it['text'] }} at {{ TimeSlots::clock($it['start']) }}</span></x-ui.link-button>
              @elseif ($it['url'])
                <x-ui.link-button variant="secondary" class="rb-availability__agenda-link" :href="$it['url']">Details<span class="sr-only"> of {{ $it['title'] }}</span></x-ui.link-button>
              @endif
            </li>
          @endforeach
        </ul>
      @else
        <p class="rb-availability__empty">{{ $staffView ? 'No bookings from your department.' : 'No classes or bookings for your block.' }}</p>
      @endif
    </div>

