  <h2 class="rb-heading rb-heading--h2">Your information</h2>
  <form method="post" action="{{ route('account.update') }}" class="rb-stack">
    @csrf
    @method('PUT')
    <x-ui.error-summary :bag="$errors->getBag('info')" />
    <div class="rb-grid">
      <div>
        <x-ui.field field-id="name" label="Name" :error="$errors->getBag('info')->first('name')">
          <x-ui.input name="name" maxlength="100" required :value="old('name', $user->name)" autocomplete="name" />
        </x-ui.field>
      </div>
      <div>
        <x-ui.field field-id="email" label="Email" :hint="$user->role === 'rep' ? 'Gmail or PUP webmail (…@iskolarngbayan.pup.edu.ph).' : null" :error="$errors->getBag('info')->first('email')">
          <x-ui.input name="email" type="email" maxlength="150" required :value="old('email', $user->email)" autocomplete="email" />
        </x-ui.field>
      </div>
      <div>
        <x-ui.field field-id="current_password" label="Current password" hint="Only needed to change your email." :error="$errors->getBag('info')->first('current_password')">
          <x-ui.input name="current_password" type="password" autocomplete="current-password" />
        </x-ui.field>

      </div>

      @if ($user->role !== 'admin')
        <div>
          <x-ui.field field-id="school_id" :label="'School ID number'.($user->role === 'lab_staff' ? ' (optional)' : '')" :error="$errors->getBag('info')->first('school_id')">
          <x-ui.input name="school_id" maxlength="20" :value="old('school_id', $user->school_id)" placeholder="e.g. 2021-00123-MN-0" :required="$user->role === 'rep'" spellcheck="false" autocapitalize="characters" />
        </x-ui.field>
        </div>
      @endif

      @if ($user->role === 'rep')
        <div>
          <span class="rb-field__label">Program and block</span>
          <p class="rb-account-readonly">{{ $user->section ? $user->program().' · '.$user->section->name : 'No block yet' }}</p>
          <p class="rb-description">Set by your admin: your block decides which classes you can book for.</p>
        </div>
      @elseif ($user->role === 'lab_staff')
        <div>
          <x-ui.field field-id="section_id" label="Program and section (optional)" hint="Your own class section, if you’re a student." :error="$errors->getBag('info')->first('section_id')">
          <x-ui.select name="section_id">
            <option value="">None</option>
            @foreach ($sections as $program => $list)
              <optgroup label="{{ $program }}">
                @foreach ($list as $section)
                  <option value="{{ $section->id }}" @selected((int) old('section_id', $user->section_id) === $section->id)>{{ $section->name }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </x-ui.select>
          </x-ui.field>

        </div>
      @endif
    </div>
    <div><x-ui.button type="submit">Save</x-ui.button></div>
  </form>
