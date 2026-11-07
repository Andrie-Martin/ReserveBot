{{-- The fields of one timetable row (add or change). $prefix: for unique ids. $row: its values, or null.
     $defaultDay: the day a new row gets. $bag: the form's errors. --}}
@use('App\Support\TimetableRules')
@php
  $value = fn (string $field) => old($field, $row[$field] ?? null);
  $day = old('day', in_array($row['day'] ?? null, TimetableRules::DAYS, true) ? $row['day'] : ($defaultDay ?? 'Mon'));
  $type = old('type', $row['type'] ?? 'Class');
  $time = fn (string $field) => old($field, TimetableRules::isTime($row[$field] ?? null) ? $row[$field] : '');
  $asTyped = fn (string $field) => $row && ($row[$field] ?? null) !== null && ! ($field === 'day' ? in_array($row['day'], TimetableRules::DAYS, true) : TimetableRules::isTime($row[$field]));
  // A refused form: mark each bad field, point it at its message, and focus the first visible one (only when the page is the answer to a refused submit).
  $fields = ['day', 'start', 'end', 'room', 'type', 'code', 'title', 'block', 'instructor', 'note'];
  $hidden = $type === 'Blocked' ? ['code', 'title', 'block', 'instructor'] : [];
  $focus = collect($fields)->first(fn ($field) => $bag->has($field) && ! in_array($field, $hidden, true));
  $bad = fn (string $field) => new \Illuminate\Support\HtmlString(($bag->has($field) ? 'aria-invalid="true" aria-describedby="'.$prefix.'-'.$field.'-error"' : '').($focus === $field ? ' autofocus' : ''));
  $msg = fn (string $field) => $bag->has($field) ? new \Illuminate\Support\HtmlString('<p class="field-error" id="'.$prefix.'-'.$field.'-error">'.e($bag->first($field)).'</p>') : '';
@endphp
@if ($bag->any())<div id="{{ $prefix }}-errors" tabindex="-1">@include('manage._errors', ['bag' => $bag])</div>@endif
<div class="mgrid">
  <div>
    <label for="{{ $prefix }}-day" class="field-label">Day</label>
    {!! $msg('day') !!}
    <select id="{{ $prefix }}-day" name="day" {!! $bad('day') !!} required>
      @foreach (TimetableRules::DAYS as $d)
        <option value="{{ $d }}" @selected($day === $d)>{{ TimetableRules::DAY_NAMES[$d] }}</option>
      @endforeach
    </select>
    @if ($asTyped('day'))<p class="hint">The file says “{{ $row['day'] }}”.</p>@endif
  </div>
  <div>
    <label for="{{ $prefix }}-start" class="field-label">Starts</label>
    {!! $msg('start') !!}
    <input id="{{ $prefix }}-start" name="start" {!! $bad('start') !!} type="time" step="300" required value="{{ $time('start') }}">
    @if ($asTyped('start'))<p class="hint">The file says “{{ $row['start'] }}”.</p>@endif
  </div>
  <div>
    <label for="{{ $prefix }}-end" class="field-label">Ends</label>
    {!! $msg('end') !!}
    <input id="{{ $prefix }}-end" name="end" {!! $bad('end') !!} type="time" step="300" required value="{{ $time('end') }}">
    @if ($asTyped('end'))<p class="hint">The file says “{{ $row['end'] }}”.</p>@endif
  </div>
  <div>
    <label for="{{ $prefix }}-room" class="field-label">Room</label>
    {!! $msg('room') !!}
    <input id="{{ $prefix }}-room" name="room" {!! $bad('room') !!} list="tt-room-list" maxlength="30" required autocomplete="off" value="{{ $value('room') }}" placeholder="e.g. CEA300">
  </div>
</div>
<fieldset class="tt-kind">
  <legend class="field-label">What is it?</legend>
  {!! $msg('type') !!}
  <label class="check"><input type="radio" name="type" value="Class" {!! $bad('type') !!} @checked($type !== 'Blocked')> A class</label>
  <label class="check"><input type="radio" name="type" value="Blocked" @checked($type === 'Blocked')> Time the room isn’t available</label>
</fieldset>
<div class="mgrid tt-class-only">
  <div>
    <label for="{{ $prefix }}-code" class="field-label">Course code</label>
    {!! $msg('code') !!}
    <input id="{{ $prefix }}-code" name="code" {!! $bad('code') !!} list="tt-code-list" maxlength="20" autocomplete="off" value="{{ $value('code') }}" placeholder="e.g. CMPE 402">
  </div>
  <div>
    <label for="{{ $prefix }}-title" class="field-label">Course title <span class="optional">(optional)</span></label>
    {!! $msg('title') !!}
    <input id="{{ $prefix }}-title" name="title" {!! $bad('title') !!} maxlength="100" value="{{ $value('title') }}" placeholder="e.g. Project Management">
  </div>
  <div>
    <label for="{{ $prefix }}-block" class="field-label">Block <span class="optional">(optional)</span></label>
    {!! $msg('block') !!}
    <input id="{{ $prefix }}-block" name="block" {!! $bad('block') !!} list="tt-block-list" maxlength="30" autocomplete="off" value="{{ $value('block') }}" placeholder="e.g. BSCPE 4-1">
  </div>
  <div>
    <label for="{{ $prefix }}-instructor" class="field-label">Instructor <span class="optional">(optional)</span></label>
    {!! $msg('instructor') !!}
    <input id="{{ $prefix }}-instructor" name="instructor" {!! $bad('instructor') !!} maxlength="60" value="{{ $value('instructor') }}">
  </div>
</div>
<div class="tt-note">
  <label for="{{ $prefix }}-note" class="field-label">Note <span class="optional">(optional; for blocked time, say why, e.g. Used by ECE)</span></label>
  {!! $msg('note') !!}
  <input id="{{ $prefix }}-note" name="note" {!! $bad('note') !!} maxlength="150" value="{{ $value('note') }}">
</div>
