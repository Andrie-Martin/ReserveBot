<div class="rb-email-notice rb-ui">
  <x-ui.alert role="status" tone="warning">
    @if (session('connect_note'))
      <p>{{ session('connect_note') }}</p>
    @else
      <p><strong>Connect your email:</strong> open the email we sent to {{ $me->email }} and click “Connect my email”{{ $me->role === 'rep' ? ' to start booking' : '' }}.</p>
      <form method="post" action="{{ route('email.resend') }}">
        @csrf
        <x-ui.button type="submit" variant="secondary">Send it again</x-ui.button>
      </form>
    @endif
  </x-ui.alert>
</div>
