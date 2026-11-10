{{-- Compact status row. Same facts and the same resend form as before; only the presentation is shared with the global notice (x-ui.status-note). --}}
@if ($user->hasConnectedEmail())
  <x-ui.status-note tone="success" title="Email connected" :heading="true" class="rb-account-email">
    <p class="rb-status__meta">{{ $user->email }}{{ $user->email_verified_at ? ' · since '.$user->email_verified_at->format('F j, Y') : '' }}</p>
    @if (session('connect_note'))
      <p role="status">{{ session('connect_note') }}</p>
    @endif
  </x-ui.status-note>
@else
  <x-ui.status-note tone="warning" title="Email not connected" :heading="true" class="rb-account-email">
    <p>Open the email we sent to <strong>{{ $user->email }}</strong> and click “Connect my email”.{{ $user->role === 'rep' ? ' You can book once it’s connected.' : '' }}</p>
    @if (session('connect_note'))
      <p role="status">{{ session('connect_note') }}</p>
    @endif
    <x-slot:actions>
      <form method="post" action="{{ route('email.resend') }}">
        @csrf
        <x-ui.button type="submit" variant="secondary">Send the link again</x-ui.button>
      </form>
    </x-slot:actions>
  </x-ui.status-note>
@endif
