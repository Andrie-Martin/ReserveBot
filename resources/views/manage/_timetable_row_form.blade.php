{{-- The fields of one timetable row (add or change). $prefix: for unique ids. $row: its values, or null.
     $defaultDay: the day a new row gets. $bag: the form's errors. --}}
@use('App\Support\TimetableRules')
@php
  $value = fn (string $field) => old($field, $row[$field] ?? null);
  $day = old('day', in_array($row['day'] ?? null, TimetableRules::DAYS, true) ? $row['day'] : ($defaultDay ?? 'Mon'));
  $type = old('type', $row['type'] ?? 'Class');
  $time = fn (string $field) => old($field, TimetableRules::isTime($row[$field] ?? null) ? $row[$field] : '');
  $asTyped = fn (string $field) => $row && ($row[$field] ?? null) !== null && ! ($field === 'day' ? in_array($row['day'], TimetableRules::DAYS, true) : TimetableRules::isTime($row[$field]));
@endphp
@include('manage._errors', ['bag' => $bag])
<div class="mgrid">
  <div>
    <label for="{{ $prefix }}-day" class="field-label">Day</label>
    <select id="{{ $prefix }}-day" name="day" required>
      @foreach (TimetableRules::DAYS as $d)
        <option value="{{ $d }}" @selected($day === $d)>{{ TimetableRules::DAY_NAMES[$d] }}</option>
      @endforeach
    </select>
    @if ($asTyped('day'))<p class="hint">The file says “{{ $row['day'] }}”.</p>@endif
  </div>
  <div>
    <label for="{{ $prefix }}-start" class="field-label">Starts</label>
    <input id="{{ $prefix }}-start" name="start" type="time" step="300" required value="{{ $time('start') }}">
    @if ($asTyped('start'))<p class="hint">The file says “{{ $row['start'] }}”.</p>@endif
  </div>
  <div>
    <label for="{{ $prefix }}-end" class="field-label">Ends</label>
    <input id="{{ $prefix }}-end" name="end" type="time" step="300" required value="{{ $time('end') }}">
    @if ($asTyped('end'))<p class="hint">The file says “{{ $row['end'] }}”.</p>@endif
  </div>
  <div>
    <label for="{{ $prefix }}-room" class="field-label">Room</label>
    <input id="{{ $prefix }}-room" name="room" list="tt-room-list" maxlength="30" required autocomplete="off" value="{{ $value('room') }}" placeholder="e.g. CEA300">
  </div>
</div>
<fieldset class="tt-kind">
  <legend class="field-label">What is it?</legend>
  <label class="check"><input type="radio" name="type" value="Class" @checked($type !== 'Blocked')> A class</label>
  <label class="check"><input type="radio" name="type" value="Blocked" @checked($type === 'Blocked')> Time the room isn’t available</label>
</fieldset>
<div class="mgrid tt-class-only">
  <div>
    <label for="{{ $prefix }}-code" class="field-label">Course code</label>
    <input id="{{ $prefix }}-code" name="code" list="tt-code-list" maxlength="20" autocomplete="off" value="{{ $value('code') }}" placeholder="e.g. CMPE 402">
  </div>
  <div>
    <label for="{{ $prefix }}-title" class="field-label">Course title <span class="optional">(optional)</span></label>
    <input id="{{ $prefix }}-title" name="title" maxlength="100" value="{{ $value('title') }}" placeholder="e.g. Project Management">
  </div>
  <div>
    <label for="{{ $prefix }}-block" class="field-label">Block <span class="optional">(optional)</span></label>
    <input id="{{ $prefix }}-block" name="block" list="tt-block-list" maxlength="30" autocomplete="off" value="{{ $value('block') }}" placeholder="e.g. BSCPE 4-1">
  </div>
  <div>
    <label for="{{ $prefix }}-instructor" class="field-label">Instructor <span class="optional">(optional)</span></label>
    <input id="{{ $prefix }}-instructor" name="instructor" maxlength="60" value="{{ $value('instructor') }}">
  </div>
</div>
<div class="tt-note">
  <label for="{{ $prefix }}-note" class="field-label">Note <span class="optional">(optional; for blocked time, say why, e.g. Used by ECE)</span></label>
  <input id="{{ $prefix }}-note" name="note" maxlength="150" value="{{ $value('note') }}">
</div>
