@php
    $canPaginate = is_object($paginator)
        && method_exists($paginator, 'currentPage')
        && method_exists($paginator, 'hasPages');

    $hasLastPage = $canPaginate && method_exists($paginator, 'lastPage');
@endphp
@if ($canPaginate && $paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $hasLastPage ? (int) $paginator->lastPage() : $current;
        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
    @endphp
    <nav class="pagination" role="navigation" aria-label="Pagination">
        <div class="pagination-inner">
            @if ($paginator->onFirstPage())
                <span class="page-btn is-disabled">Prev</span>
            @else
                <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a>
            @endif

            @if ($hasLastPage)
                <div class="page-numbers">
                    @if ($start > 1)
                        <a class="page-num" href="{{ $paginator->url(1) }}">1</a>
                        @if ($start > 2)
                            <span class="page-ellipsis">…</span>
                        @endif
                    @endif

                    @for ($page = $start; $page <= $end; $page++)
                        @if ($page == $current)
                            <span class="page-num is-active">{{ $page }}</span>
                        @else
                            <a class="page-num" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                        @endif
                    @endfor

                    @if ($end < $last)
                        @if ($end < $last - 1)
                            <span class="page-ellipsis">…</span>
                        @endif
                        <a class="page-num" href="{{ $paginator->url($last) }}">{{ $last }}</a>
                    @endif
                </div>
            @endif

            @if ($paginator->hasMorePages())
                <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="page-btn is-disabled">Next</span>
            @endif
        </div>
        <div class="page-meta">
            @if ($hasLastPage)
                Page {{ $current }} of {{ $last }}
            @else
                Page {{ $current }}
            @endif
        </div>
    </nav>
@endif
