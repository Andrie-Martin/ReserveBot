{{-- The errors of one form ($bag: its error bag) --}}
@if ($bag->any())
  <div class="error" role="alert">
    <ul>
      @foreach ($bag->all() as $message)
        <li>{{ $message }}</li>
      @endforeach
    </ul>
  </div>
@endif
