{{-- Presentation only: caller owns headings, forms, messages and navigation. --}}
<x-ui.card {{ $attributes->class(['rb-auth-card', 'rb-ui', 'rb-stack']) }}>
  @isset($heading)<div class="rb-auth-card__intro">{{ $heading }}</div>@endisset
  {{ $slot }}
  @isset($footer)<div class="rb-auth-card__footer">{{ $footer }}</div>@endisset
</x-ui.card>
