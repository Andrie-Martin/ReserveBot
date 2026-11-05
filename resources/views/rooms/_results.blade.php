<section class="rb-availability__results-section" aria-labelledby="room-results-title">
  @include('rooms._search')
    <h3 class="rb-availability__list-title" id="room-results-title">Free times in each room</h3>
    @if ($now !== null && $slots && $now > $slots[0])
      <p class="rb-availability__muted rb-availability__small">Times that have already started today aren’t shown as free.</p>
    @endif

    <ul class="rb-availability__roomlist">
      @forelse ($roomList as $row)
        @include('rooms._room')
      @empty
        <li><p class="rb-availability__empty">No rooms listed for this department.</p></li>
      @endforelse
    </ul>
</section>
