@use('App\Support\TimeSlots')
              @php
                $items = $day['items'];
                $shown = count($items) > 3 ? 2 : 3;   // keep every day to three lines
              @endphp
              <td @class(['rb-availability__day', 'st-'.$day['state'], 'rb-availability__out' => ! $day['inMonth'], 'rb-availability__today' => $day['isToday'], 'rb-availability__selected' => $day['isSelected'], 'rb-availability__pick' => $day['url']])>
                @if ($day['url'])
                  <a class="rb-availability__cell" href="{{ $day['url'] }}" @if ($day['isSelected']) aria-current="date" @endif>
                @else
                  <div class="rb-availability__cell">
                @endif
                    <span class="rb-availability__daynum" aria-hidden="true">@if ($day['date']->day === 1 && ! $day['inMonth'])<span class="rb-availability__mon">{{ $day['date']->format('M') }} </span>@endif{{ $day['date']->day }}</span>
                    <span class="sr-only">{{ $day['date']->format('l, F j') }}{{ $day['isToday'] ? ', today' : '' }}{{ $day['isSelected'] ? ', selected' : '' }}{{ $day['state'] === 'closed' ? ', closed' : '' }}</span>
                    @if ($day['state'] === 'closed')
                      <span class="rb-availability__daynote">Closed</span>
                    @endif
                    @if ($items)
                      <span class="rb-availability__evts">
                        @foreach (array_slice($items, 0, $shown) as $it)
                          @php $booked = $it['kind'] === 'class' && in_array($it['status'], ['Confirmed', 'InUse'], true); @endphp
                          <span @class(['rb-availability__evt', 'k-'.$it['kind'], 'rb-availability__pending' => $it['status'] === 'Pending', 'rb-availability__booked' => $booked, 'rb-availability__freed' => ! empty($it['freed'])]) title="{{ $it['tip'] }}{{ ! empty($it['check']) ? ' · '.$it['check'] : '' }}">
                            <span class="rb-availability__t">{!! $clock !!}<b>{{ TimeSlots::clock($it['start']) }}</b> <span class="rb-availability__x">{{ $it['text'] }}</span></span>
                            <span class="rb-availability__w">{!! $it['noRoom'] ? $box : $pin !!}{{ $it['room'] }}</span>
                            @if ($booked)<span class="rb-availability__ok" aria-hidden="true">✓</span>@endif
                            @if ($it['tag'])<span class="sr-only">({{ $it['tag'] }})</span>@endif
                            @if (! empty($it['check']))<span class="sr-only">({{ $it['check'] }})</span>@endif
                          </span>
                        @endforeach
                        @if (count($items) > $shown)
                          <span class="rb-availability__more">+{{ count($items) - $shown }} more</span>
                        @endif
                      </span>
                      <span class="rb-availability__dots" aria-hidden="true">
                        @foreach (array_slice($items, 0, 4) as $it)<i @class(['k-'.$it['kind'], 'rb-availability__pending' => $it['status'] === 'Pending'])></i>@endforeach
                      </span>
                    @endif
                @if ($day['url'])
                  </a>
                @else
                  </div>
                @endif
                @if ($items)
                  <a class="rb-availability__event-count" href="#events-{{ $day['date']->toDateString() }}"><span>{{ count($items) }}<span class="rb-availability__event-word"> {{ count($items) === 1 ? 'event' : 'events' }}</span></span><span class="sr-only"> on {{ $day['date']->format('F j') }}</span></a>
                @endif
              </td>