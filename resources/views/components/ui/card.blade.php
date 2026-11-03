<div {{ $attributes->class(['rb-card']) }}>
  @isset($heading)<div class="rb-card__heading">{{ $heading }}</div>@endisset
  {{ $slot }}
  @isset($actions)<div class="rb-actions rb-card__actions">{{ $actions }}</div>@endisset
</div>
