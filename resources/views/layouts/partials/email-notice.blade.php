{{-- Shown on every workspace page until the account's email is connected (not on /account, which has its own status row). --}}
<div class="rb-email-notice rb-ui">
  @if (session('connect_note'))
    <x-ui.status-note role="status" tone="warning" title="Email not connected">
      <p>{{ session('connect_note') }}</p>
    </x-ui.status-note>
  @else
    <x-ui.status-note role="status" tone="warning" title="Email not connected">
      <p>Open the email we sent to {{ $me->email }} and click “Connect my email”{{ $me->role === 'rep' ? ' to start booking' : '' }}.</p>
      <x-slot:actions>
        <form method="post" action="{{ route('email.resend') }}">
          @csrf
          <x-ui.button type="submit" variant="secondary">Send it again</x-ui.button>
        </form>
      </x-slot:actions>
    </x-ui.status-note>
  @endif
</div>
