@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="disabled">&larr; Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; Previous</a>
        @endif

        <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rarr;</a>
        @else
            <span class="disabled">Next &rarr;</span>
        @endif
    </nav>
@endif
