{{-- Phase 10: the two answer buttons for one class meeting ($m) on the rep home page or the Today page.
     $route: 'classes.answer' (reps) or 'classes.mark' (lab staff and admins). --}}
@php $ended = $m['end']->isPast(); @endphp
@if ($m['state'] !== 'booked' && ! $ended)
  {{-- A meeting booked as a class session keeps its room; its tag already says so --}}
  <form method="post" action="{{ route($route, ['class' => $m['class']->id, 'date' => $m['date']->toDateString()]) }}" class="answer-buttons">
    @csrf
    <button type="submit" name="answer" value="f2f" @class(['btn-mini', 'on' => $m['state'] === 'f2f']) @disabled($m['state'] === 'f2f')>Face-to-face<span class="sr-only">: {{ $m['class']->course?->code }} {{ $m['date']->format('M j') }}</span></button>
    <button type="submit" name="answer" value="online" @class(['btn-mini', 'on' => $m['state'] === 'online']) @disabled($m['state'] === 'online')>Online<span class="sr-only">: {{ $m['class']->course?->code }} {{ $m['date']->format('M j') }}</span></button>
  </form>
@endif
