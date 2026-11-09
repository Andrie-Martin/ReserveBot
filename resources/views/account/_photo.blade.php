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
      <div class="rb-account-photo__forms">
        {{-- Both buttons sit in one row. "Remove photo" belongs to the separate DELETE form below through its form attribute,
             so it can never submit the upload form (and pressing Enter in the file field still means "Replace/Upload"). --}}
        <form id="photo-upload-form" method="post" action="{{ route('account.photo') }}" enctype="multipart/form-data">
          @csrf
          <x-ui.field field-id="photo" label="Photo" :error="$errors->getBag('photo')->first('photo')">
            <x-ui.input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-describedby="photo-guidance" />
          </x-ui.field>
          <div class="rb-account-photo__actions">
            <x-ui.button type="submit">{{ $user->photo_path ? 'Replace photo' : 'Upload photo' }}</x-ui.button>
            @if ($user->photo_path)
              <x-ui.button type="submit" variant="danger-outline" form="photo-remove-form">Remove photo</x-ui.button>
            @endif
          </div>
        </form>
        @if ($user->photo_path)
          <form id="photo-remove-form" method="post" action="{{ route('account.photo.remove') }}">
            @csrf
            @method('DELETE')
          </form>
        @endif
      </div>
      <p id="photo-guidance" class="rb-description">A clear photo of your face (JPG, PNG or WebP, up to 5 MB). Lab staff see it when you pick up a key.</p>
    </div>
  </div>
