@php
    $canPaginate = is_object($paginator)
        && method_exists($paginator, 'hasPages')
        && method_exists($paginator, 'onFirstPage')
        && method_exists($paginator, 'currentPage');
@endphp
@if ($canPaginate && $paginator->hasPages())
    @php
        $elements = method_exists($paginator, 'elements') ? $paginator->elements() : [];
        $hasLastPage = method_exists($paginator, 'lastPage');
    @endphp
    <nav class="pagination" role="navigation" aria-label="Pagination">
        <div class="pagination-inner">
            @if ($paginator->onFirstPage())
                <span class="page-btn is-disabled">Prev</span>
            @else
                <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a>
            @endif

            @if (!empty($elements))
                <div class="page-numbers">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="page-ellipsis">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span class="page-num is-active">{{ $page }}</span>
                                @else
                                    <a class="page-num" href="{{ $url }}">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
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
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
            @else
                Page {{ $paginator->currentPage() }}
            @endif
        </div>
    </nav>
@endif
