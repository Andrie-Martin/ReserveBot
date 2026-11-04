<x-ui.alert :tone="$user->hasConnectedEmail() ? 'success' : 'warning'" class="rb-account-email rb-stack">
  @if ($user->hasConnectedEmail())
    <p><x-ui.badge label="Connected" tone="success" /> Your email {{ $user->email }} is connected{{ $user->email_verified_at ? ' since '.$user->email_verified_at->format('F j, Y') : '' }}.</p>
  @else
    <p><x-ui.badge label="Not connected yet" tone="warning" /> Open the email we sent to <strong>{{ $user->email }}</strong> and click “Connect my email”.{{ $user->role === 'rep' ? ' You can book once it’s connected.' : '' }}</p>
    <form method="post" action="{{ route('email.resend') }}">
      @csrf
      <x-ui.button type="submit" variant="secondary">Send the link again</x-ui.button>
    </form>
  @endif
  @if (session('connect_note'))
    <p role="status">{{ session('connect_note') }}</p>
  @endif
</x-ui.alert>
