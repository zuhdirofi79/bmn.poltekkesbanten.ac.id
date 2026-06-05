@php
  $active = $active ?? [];
  $align = $align ?? 'left';
  $isActive = is_array($active) && count($active) > 0;
@endphp
<th class="pa-col-filter-th text-{{ $align === 'end' ? 'end' : 'start' }}">
  <div class="pa-col-filter-head {{ $align === 'end' ? 'justify-content-end' : '' }}">
    <span class="pa-col-filter-label">{{ $label }}</span>
    <button type="button"
            class="pa-col-filter-btn {{ $isActive ? 'is-active' : '' }}"
            data-col="{{ $column }}"
            aria-label="Filter {{ $label }}"
            aria-expanded="false">
      <i class="ti ti-filter"></i>
      @if($isActive)
        <span class="pa-col-filter-badge" title="Filter aktif">●</span>
      @endif
    </button>
  </div>
  <div class="pa-col-filter-dropdown"
       data-col="{{ $column }}"
       data-param="{{ $param }}"
       role="dialog"
       aria-label="Filter {{ $label }}"
       hidden>
    <input type="search"
           class="form-control form-control-sm pa-col-filter-search"
           placeholder="Cari..."
           autocomplete="off">
    <label class="pa-col-filter-select-all">
      <input type="checkbox" class="pa-col-filter-check-all">
      <span>Pilih Semua</span>
    </label>
    <div class="pa-col-filter-list">
      @forelse($options as $value => $optionLabel)
        <label class="pa-col-filter-option" data-text="{{ strtolower((string) $optionLabel) }}">
          <input type="checkbox"
                 class="pa-col-filter-item"
                 value="{{ $value }}"
                 @checked(in_array((string) $value, array_map('strval', $active), true))>
          <span>{{ $optionLabel }}</span>
        </label>
      @empty
        <div class="pa-col-filter-empty text-muted small px-2 py-1">Tidak ada opsi</div>
      @endforelse
    </div>
    <div class="pa-col-filter-actions">
      <button type="button" class="btn btn-sm btn-prs-blue pa-col-filter-apply">Terapkan</button>
      <button type="button" class="btn btn-sm btn-outline-secondary pa-col-filter-reset-col">Reset</button>
    </div>
  </div>
</th>
