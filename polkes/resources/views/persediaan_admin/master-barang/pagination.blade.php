@if ($paginator->hasPages())
  <nav class="master-barang-pagination" role="navigation" aria-label="Pagination Navigation">
    <p class="master-barang-pagination__summary">
      Showing
      <span>{{ $paginator->firstItem() }}</span>
      to
      <span>{{ $paginator->lastItem() }}</span>
      of
      <span>{{ $paginator->total() }}</span>
      results
    </p>

    <ul class="pagination mb-0">
      @if ($paginator->onFirstPage())
        <li class="page-item disabled" aria-disabled="true">
          <span class="page-link" aria-hidden="true">
            @include('persediaan_admin.master-barang.partials.pagination-chevron', ['direction' => 'prev'])
            Previous
          </span>
        </li>
      @else
        <li class="page-item">
          <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
            @include('persediaan_admin.master-barang.partials.pagination-chevron', ['direction' => 'prev'])
            Previous
          </a>
        </li>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
            @else
              <li class="page-item">
                <a class="page-link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
              </li>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <li class="page-item">
          <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
            Next
            @include('persediaan_admin.master-barang.partials.pagination-chevron', ['direction' => 'next'])
          </a>
        </li>
      @else
        <li class="page-item disabled" aria-disabled="true">
          <span class="page-link" aria-hidden="true">
            Next
            @include('persediaan_admin.master-barang.partials.pagination-chevron', ['direction' => 'next'])
          </span>
        </li>
      @endif
    </ul>
  </nav>
@endif

@once
  <style>
    .master-barang-pagination {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }

    .master-barang-pagination__summary {
      margin: 0;
      font-size: 14px;
      line-height: 1.4;
      color: #5a6a85;
    }

    .master-barang-pagination .pagination {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 6px;
      margin: 0;
    }

    .master-barang-pagination .page-item {
      margin: 0;
    }

    .master-barang-pagination .page-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      min-height: 36px;
      min-width: 36px;
      padding: 6px 12px;
      font-size: 14px;
      line-height: 1.2;
      white-space: nowrap;
    }

    .master-barang-pagination .page-item.disabled .page-link {
      pointer-events: none;
    }

    .master-barang-pagination svg {
      width: 16px !important;
      height: 16px !important;
      max-width: 16px;
      max-height: 16px;
      flex: 0 0 16px;
      display: inline-block;
      vertical-align: middle;
    }

    @media (max-width: 576px) {
      .master-barang-pagination .page-link {
        min-height: 34px;
        padding: 6px 10px;
        font-size: 13px;
      }
    }
  </style>
@endonce
