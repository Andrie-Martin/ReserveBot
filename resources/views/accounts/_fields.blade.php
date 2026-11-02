{{-- Phase 9: the fields shared by "Add an account" and editing one.
     Needs $prefix (for unique ids), $account (a User, or null when adding), $blocks and $sections. --}}
@php
  $role = old('role', $account?->role ?? 'rep');
  $sectionId = (int) old('section_id', $account?->role === 'lab_staff' ? $account?->section_id : null);
  $blockId = (int) old('block_id', $account?->role === 'rep' ? $account?->section_id : null);
@endphp
<fieldset class="role-pick">
  <legend class="field-label">Role</legend>
  <label class="check"><input type="radio" name="role" value="rep" @checked($role === 'rep')> Block rep</label>
  <label class="check"><input type="radio" name="role" value="lab_staff" @checked($role === 'lab_staff')> Lab staff</label>
</fieldset>
<div class="mgrid">
  <div>
    <label for="{{ $prefix }}-name" class="field-label">Name</label>
    <input id="{{ $prefix }}-name" name="name" maxlength="100" required value="{{ old('name', $account?->name) }}" placeholder="e.g. Juan Dela Cruz">
  </div>
  <div>
    <label for="{{ $prefix }}-email" class="field-label">Email</label>
    <input id="{{ $prefix }}-email" name="email" type="email" maxlength="150" required value="{{ old('email', $account?->email) }}">
    <p class="hint for-rep">Reps: Gmail or PUP webmail (<span class="nowrap">…@iskolarngbayan.pup.edu.ph</span>).</p>
  </div>
  <div class="for-rep">
    <label for="{{ $prefix }}-block" class="field-label">Block</label>
    <select id="{{ $prefix }}-block" name="block_id">
      <option value="">Pick the block…</option>
      @foreach ($blocks as $block)
        <option value="{{ $block->id }}" @selected($blockId === $block->id)>{{ $block->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="for-staff">
    <label for="{{ $prefix }}-section" class="field-label">Program and section <span class="optional">(optional)</span></label>
    <select id="{{ $prefix }}-section" name="section_id">
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
  <div>
    <label for="{{ $prefix }}-school" class="field-label">School ID number <span class="optional">(optional)</span></label>
    <input id="{{ $prefix }}-school" name="school_id" maxlength="20" value="{{ old('school_id', $account?->school_id) }}" placeholder="e.g. 2021-00123-MN-0" spellcheck="false" autocapitalize="characters">
  </div>
</div>
