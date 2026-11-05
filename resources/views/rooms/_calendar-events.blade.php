@use('App\Support\TimeSlots')
<ul class="rb-availability__event-list">
  @foreach ($items as $event)
    <li>
      <strong>{{ TimeSlots::span($event['start'], $event['end']) }} · {{ $event['text'] }}</strong>
      <span>{{ $event['room'] }}</span>
      <span>{{ $event['tip'] }}</span>
      @if ($event['tag'])<span>{{ $event['tag'] }}</span>@endif
      @if (! empty($event['check']))<span>{{ $event['check'] }}</span>@endif
    </li>
  @endforeach
</ul>
