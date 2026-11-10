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
        @php
          // Numbered links: first, last, and two either side of the current page, with a gap marker between runs.
          $current = $paginator->currentPage();
          $last = $paginator->lastPage();
          $numbers = collect(range(1, $last))->filter(fn ($n) => $n === 1 || $n === $last || abs($n - $current) <= 2)->values();
        @endphp
        <span class="sr-only">Page {{ $current }} of {{ $last }}</span>
        <ol class="rb-pager__pages">
          @foreach ($numbers as $i => $n)
            @if ($i > 0 && $n - $numbers[$i - 1] > 1)
              <li class="rb-pager__gap" aria-hidden="true">…</li>
            @endif
            <li>
              @if ($n === $current)
                <span class="rb-pager__num rb-pager__num--current" aria-current="page"><span class="sr-only">Page </span>{{ $n }}</span>
              @else
                <a class="rb-pager__num" href="{{ $paginator->url($n) }}"><span class="sr-only">Page </span>{{ $n }}</a>
              @endif
            </li>
          @endforeach
        </ol>
        @if ($paginator->hasMorePages())
          <x-ui.link-button variant="secondary" size="compact" :href="$paginator->nextPageUrl()" rel="next">Next<span class="sr-only"> page</span> ›</x-ui.link-button>
        @else
          <span class="rb-button rb-button--secondary rb-button--compact" aria-disabled="true">Next ›</span>
        @endif
      </div>
    @endif
  </nav>
@endif
