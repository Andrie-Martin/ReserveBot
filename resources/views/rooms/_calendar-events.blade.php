@use('App\Support\TimeSlots')
<ol class="rb-availability__event-list">
  @foreach ($items as $event)
    <li @class(['rb-availability__event-card', 'k-'.$event['kind'], 'rb-availability__pending' => ($event['status'] ?? null) === 'Pending'])>
      <p class="rb-availability__event-time">{{ TimeSlots::span($event['start'], $event['end']) }}</p>
      <div class="rb-availability__event-body">
        <p class="rb-availability__event-title">{{ $event['text'] }}</p>
        <p class="rb-availability__event-room">{{ $event['room'] }}</p>
        <p class="rb-availability__event-tip">{{ $event['tip'] }}</p>
        @if ($event['tag'] || ! empty($event['check']))
          <p class="rb-availability__event-tags">
            @if ($event['tag'])<span class="rb-availability__event-tag">{{ $event['tag'] }}</span>@endif
            @if (! empty($event['check']))<span class="rb-availability__event-tag">{{ $event['check'] }}</span>@endif
          </p>
        @endif
      </div>
    </li>
  @endforeach
</ol>
