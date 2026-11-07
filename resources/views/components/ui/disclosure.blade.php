@props(['summary', 'open' => false])
<details {{ $attributes->class(['rb-disclosure']) }} @if ($open) open @endif>
  <summary>{{ $summary }}</summary>
  <div class="rb-disclosure__content">{{ $slot }}</div>
</details>
