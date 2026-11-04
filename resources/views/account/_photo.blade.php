  <div class="rb-account-photo">
    <x-avatar :user="$user" size="lg" />
    <div class="rb-account-photo__content">
      <p class="rb-account-photo__name">{{ $user->name }}</p>
      <p class="rb-description">{{ $user->roleLabel() }} · {{ $user->department->name }}</p>
      <x-ui.error-summary :bag="$errors->getBag('photo')" />
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
            <x-ui.button type="submit" variant="quiet">Remove photo</x-ui.button>
          </form>
        @endif
      </div>
      <p id="photo-guidance" class="rb-description">A clear photo of your face (JPG, PNG or WebP, up to 5 MB). Lab staff see it when you pick up a key.</p>
    </div>
  </div>
