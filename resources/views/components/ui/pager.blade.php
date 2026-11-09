@props(['paginator', 'noun' => 'items'])
{{-- Server-side page links. The paginator already carries the query string and fragment (set by the controller). --}}
@if ($paginator->total() > 0)
  <nav {{ $attributes->class(['rb-pager']) }} aria-label="{{ ucfirst($noun) }} pages">
    <p class="rb-pager__info">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $noun }}</p>
    @if ($paginator->hasPages())
      <div class="rb-pager__nav">
        @if ($paginator->onFirstPage())
          <span class="rb-button rb-button--secondary rb-button--compact" aria-disabled="true">‹ Previous</span>
        @else
          <x-ui.link-button variant="secondary" size="compact" :href="$paginator->previousPageUrl()" rel="prev">‹ Previous<span class="sr-only"> page</span></x-ui.link-button>
        @endif
        <span class="rb-pager__page" aria-current="page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
          <x-ui.link-button variant="secondary" size="compact" :href="$paginator->nextPageUrl()" rel="next">Next<span class="sr-only"> page</span> ›</x-ui.link-button>
        @else
          <span class="rb-button rb-button--secondary rb-button--compact" aria-disabled="true">Next ›</span>
        @endif
      </div>
    @endif
  </nav>
@endif
