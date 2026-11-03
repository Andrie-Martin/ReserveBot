@props(['title', 'description' => null])
<div {{ $attributes->class(['rb-page-header']) }}>
  <div class="rb-page-header__text">
    <h1 class="rb-heading rb-heading--h1">{{ $title }}</h1>
    @if (filled($description))<p class="rb-description">{{ $description }}</p>@endif
  </div>
  @isset($actions)<div class="rb-actions">{{ $actions }}</div>@endisset
</div>
