@props(['label', 'value', 'note' => null])
<x-ui.card {{ $attributes->class(['rb-stat-card']) }}>
  <dl class="rb-stat-card__list">
    <dt class="rb-stat-card__label">{{ $label }}</dt>
    <dd class="rb-stat-card__value">{{ $value }}</dd>
  </dl>
  @if ($note)<p class="rb-stat-card__note">{{ $note }}</p>@endif
</x-ui.card>
