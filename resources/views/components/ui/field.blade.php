@props(['fieldId', 'label', 'hint' => null, 'error' => null])
<div {{ $attributes->class(['rb-field']) }}>
  <label class="rb-field__label" for="{{ $fieldId }}">{{ $label }}</label>
  {{ $slot }}
  @if (filled($hint))<p class="rb-field__hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>@endif
  @if (filled($error))<p class="rb-field__error" id="{{ $fieldId }}-error">{{ $error }}</p>@endif
</div>
