@aware(['fieldId' => null, 'hint' => null, 'error' => null])
@props(['id' => null, 'invalid' => false, 'type' => 'text', 'tag' => 'input'])
@php
  $controlId = $id ?? $fieldId;
  $invalid = $invalid || filled($error);
  $descriptions = trim(implode(' ', array_filter([
      $attributes->get('aria-describedby'),
      $fieldId && filled($hint) ? $fieldId.'-hint' : null,
      $fieldId && filled($error) ? $fieldId.'-error' : null,
  ])));
  $controlAttributes = $attributes->class(['rb-control'])->merge(array_filter([
      'id' => $controlId,
      'aria-describedby' => $descriptions ?: null,
      'aria-invalid' => $invalid ? 'true' : null,
  ], fn ($value) => $value !== null));
  if ($invalid) $controlAttributes = $controlAttributes->except('aria-invalid')->merge(['aria-invalid' => 'true']);
  // Compose the caller's description IDs with the field's hint/error IDs.
  if ($descriptions) $controlAttributes = $controlAttributes->except('aria-describedby')->merge(['aria-describedby' => $descriptions]);
@endphp
@if ($tag === 'select')
  <select {{ $controlAttributes }}>{{ $slot }}</select>
@elseif ($tag === 'textarea')
  <textarea {{ $controlAttributes }}>{{ $slot }}</textarea>
@else
  <input type="{{ $type }}" {{ $controlAttributes }}>
@endif
