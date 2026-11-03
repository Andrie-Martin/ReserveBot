@props(['label', 'value'])
<x-ui.card {{ $attributes->class(['rb-stat-card']) }}>
  <dl class="rb-stat-card__list">
    <dt class="rb-stat-card__label">{{ $label }}</dt>
    <dd class="rb-stat-card__value">{{ $value }}</dd>
  </dl>
</x-ui.card>
