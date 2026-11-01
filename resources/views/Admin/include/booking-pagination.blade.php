@if ($paginator->hasPages())
    <nav class="booking-pagination" aria-label="Booking pagination">
        @if ($paginator->onFirstPage())
            <span class="page-arrow" aria-disabled="true" aria-label="Previous page">&lsaquo;</span>
        @else
            <a class="page-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&lsaquo;</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-number" aria-disabled="true">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-number active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page-number" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="page-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&rsaquo;</a>
        @else
            <span class="page-arrow" aria-disabled="true" aria-label="Next page">&rsaquo;</span>
        @endif
    </nav>
@endif
