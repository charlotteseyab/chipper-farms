<nav role="navigation" aria-label="Pagination" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm font-medium text-gray-600">
            Page <span class="font-semibold text-gray-900">{{ $paginator->currentPage() }}</span>
            of <span class="font-semibold text-gray-900">{{ $paginator->lastPage() }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-1">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-4 py-2 bg-white text-gray-400 border border-gray-200 rounded-lg cursor-not-allowed">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">Previous</a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();
                $startPage = max(1, $currentPage - 2);
                $endPage = min($lastPage, $currentPage + 2);
            @endphp

            {{-- First Page --}}
            @if ($startPage > 1)
                <a href="{{ $paginator->url(1) }}" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">1</a>
                @if ($startPage > 2)
                    <span class="px-3 py-2 text-gray-500">...</span>
                @endif
            @endif

            {{-- Page Numbers Around Current Page --}}
            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page == $currentPage)
                    <span class="inline-flex items-center px-4 py-2 bg-blue-600 text-white border border-blue-600 rounded-lg font-medium">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">{{ $page }}</a>
                @endif
            @endfor

            {{-- Last Page --}}
            @if ($endPage < $lastPage)
                @if ($endPage < $lastPage - 1)
                    <span class="px-3 py-2 text-gray-500">...</span>
                @endif
                <a href="{{ $paginator->url($lastPage) }}" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">{{ $lastPage }}</a>
            @endif
            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">Next</a>
            @else
                <span class="inline-flex items-center px-4 py-2 bg-white text-gray-400 border border-gray-200 rounded-lg cursor-not-allowed">Next</span>
            @endif
        </div>
</nav>
