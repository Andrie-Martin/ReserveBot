{{-- Phase 9: the fields shared by "Add an account" and editing one.
     Needs $prefix (for unique ids), $account (a User, or null when adding), $blocks and $sections.
     A block account belongs to its block (one per block); its "name" is the block's representative. --}}
@php
  // After a failed save: $bag (this form's errors) marks the invalid fields, and $focus names the first one, which takes focus.
  $bag = $bag ?? null;
  $focus = $focus ?? null;
  $invalid = fn (string $field) => ($bag?->has($field) ? 'aria-invalid="true" aria-describedby="'.$prefix.'-errors"' : '').($focus === $field ? ' autofocus' : '');
  $role = old('role', $account?->role ?? 'rep');
  $sectionId = (int) old('section_id', $account?->role === 'lab_staff' ? $account?->section_id : null);
  $blockId = (int) old('block_id', $account?->role === 'rep' ? $account?->section_id : null);
@endphp
<fieldset class="role-pick">
  <legend class="field-label">Role</legend>
  <label class="check"><input type="radio" name="role" value="rep" @checked($role === 'rep')> Block account</label>
  <label class="check"><input type="radio" name="role" value="lab_staff" @checked($role === 'lab_staff')> Lab staff</label>
</fieldset>
<div class="mgrid">
  <div>
    <label for="{{ $prefix }}-name" class="field-label"><span class="for-rep">Representative’s name</span><span class="for-staff">Name</span></label>
    <input id="{{ $prefix }}-name" name="name" maxlength="100" required {!! $invalid('name') !!} value="{{ old('name', $account?->name) }}" placeholder="e.g. Juan Dela Cruz">
    <p class="hint for-rep">Who represents the block now. It can be changed later, here or by the block in My account.</p>
  </div>
  <div>
    <label for="{{ $prefix }}-email" class="field-label">Email</label>
    <input id="{{ $prefix }}-email" name="email" type="email" maxlength="150" required {!! $invalid('email') !!} value="{{ old('email', $account?->email) }}">
    <p class="hint for-rep">Block accounts: Gmail or PUP webmail (<span class="nowrap">…@iskolarngbayan.pup.edu.ph</span>).</p>
  </div>
  <div class="for-rep">
    <label for="{{ $prefix }}-block" class="field-label">Block</label>
    <select id="{{ $prefix }}-block" name="block_id" {!! $invalid('block_id') !!}>
      <option value="">Pick the block…</option>
      @foreach ($blocks as $block)
        <option value="{{ $block->id }}" @selected($blockId === $block->id)>{{ $block->name }}</option>
      @endforeach
    </select>
    <p class="hint">The account belongs to this block. Each block has one account.</p>
  </div>
  <div class="for-staff">
    <label for="{{ $prefix }}-section" class="field-label">Program and section <span class="optional">(optional)</span></label>
    <select id="{{ $prefix }}-section" name="section_id" {!! $invalid('section_id') !!}>
      <option value="">None</option>
      @foreach ($sections as $program => $list)
        <optgroup label="{{ $program }}">
          @foreach ($list as $section)
            <option value="{{ $section->id }}" @selected($sectionId === $section->id)>{{ $section->name }}</option>
          @endforeach
        </optgroup>
      @endforeach
    </select>
  </div>
</div>
