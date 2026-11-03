@props(['label'])
<div {{ $attributes->class(['rb-table-container'])->merge(['role' => 'region', 'aria-label' => $label, 'tabindex' => '0']) }}>{{ $slot }}</div>
