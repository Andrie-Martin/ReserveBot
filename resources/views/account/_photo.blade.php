  <div class="rb-account-photo">
    <h2 class="rb-heading rb-heading--h2">Profile photo</h2>
    <div class="rb-account-photo__head">
      <x-avatar :user="$user" size="lg" />
      <div class="rb-account-photo__identity">
        <p class="rb-account-photo__name">{{ $user->name }}</p>
        <p class="rb-description">{{ $user->roleLabel() }} · {{ $user->department->name }}</p>
      </div>
    </div>
    <div class="rb-account-photo__content">
      <x-ui.error-summary :bag="$errors->getBag('photo')" />
      {{-- Two independent native forms, each with its own submit button. CSS lays them out as one action row (the upload form's
           children join the grid), so Replace/Upload and Remove share a row, height and radius without nesting or form="" tricks. --}}
      <div class="rb-account-photo__forms">
        <form method="post" action="{{ route('account.photo') }}" enctype="multipart/form-data">
          @csrf
          <x-ui.field field-id="photo" label="Photo" :error="$errors->getBag('photo')->first('photo')">
            <x-ui.input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-describedby="photo-guidance" />
          </x-ui.field>
          <x-ui.button type="submit">{{ $user->photo_path ? 'Replace photo' : 'Upload photo' }}</x-ui.button>
        </form>
        @if ($user->photo_path)
          <form method="post" action="{{ route('account.photo.remove') }}">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" variant="danger-outline">Remove photo</x-ui.button>
          </form>
        @endif
      </div>
      <p id="photo-guidance" class="rb-description">A clear photo of your face (JPG, PNG or WebP, up to 5 MB). Lab staff see it when you pick up a key.</p>
    </div>
  </div>
