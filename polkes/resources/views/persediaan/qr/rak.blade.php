@extends('layouts.persediaan_qr')

@section('page_title', 'Rak ' . $rak->kode_rak)

@section('content')
<div class="prs-rak-head">
  <div class="text-muted small">RAK</div>
  <h1 class="h4 mb-0">{{ $rak->kode_rak }}</h1>
  <p class="mb-0">{{ $rak->nama_rak }}</p>
</div>

<input type="search" id="rakSearch" class="form-control mt-3" placeholder="Cari barang...">

<form method="POST" action="{{ route('public.persediaan.rak.confirm', $rak->qr_token) }}" id="rakForm">
  @csrf
  <div class="prs-rak-list" id="rakList">
    @forelse($items as $row)
      @php $b = $row['barang']; $stok = (int) $row['stok']; $val = (int) ($draft[$b->id] ?? 0); @endphp
      <div class="prs-rak-card" data-name="{{ strtolower($b->nama_barang.' '.$b->kode_barang) }}">
        <div>
          <strong>{{ $b->nama_barang }}</strong>
          <div class="small text-muted">Kode: {{ $b->kode_barang }}</div>
          <div class="small">Stok tersedia: {{ number_format($stok, 0, ',', '.') }} {{ $b->satuan }}</div>
        </div>
        <div class="prs-qty" data-max="{{ $stok }}">
          <button type="button" class="prs-qty-btn" data-act="minus" @disabled($stok <= 0)>−</button>
          <input type="number" name="qty[{{ $b->id }}]" min="0" max="{{ $stok }}" value="{{ $val }}"
                 class="prs-qty-input" @disabled($stok <= 0) inputmode="numeric">
          <button type="button" class="prs-qty-btn" data-act="plus" @disabled($stok <= 0)>+</button>
        </div>
      </div>
    @empty
      <p class="text-muted text-center py-4">Belum ada barang di rak ini.</p>
    @endforelse
  </div>

  <div class="prs-sticky-bar">
    <div class="small" id="rakSummary">0 jenis barang dipilih</div>
    <button type="submit" class="btn btn-prs-blue prs-touch" id="lanjutBtn" disabled>Lanjutkan</button>
  </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('rakForm');
  var search = document.getElementById('rakSearch');
  var summary = document.getElementById('rakSummary');
  var lanjut = document.getElementById('lanjutBtn');

  function clamp(el) {
    var wrap = el.closest('.prs-qty');
    var max = parseInt(wrap.getAttribute('data-max'), 10) || 0;
    var n = parseInt(el.value, 10) || 0;
    if (n < 0) n = 0;
    if (n > max) n = max;
    el.value = String(n);
  }

  function recount() {
    var count = 0;
    form.querySelectorAll('.prs-qty-input').forEach(function (el) {
      if ((parseInt(el.value, 10) || 0) > 0) count++;
    });
    summary.textContent = count + ' jenis barang dipilih';
    lanjut.disabled = count === 0;
  }

  form.addEventListener('click', function (e) {
    var btn = e.target.closest('.prs-qty-btn');
    if (!btn) return;
    var wrap = btn.closest('.prs-qty');
    var input = wrap.querySelector('.prs-qty-input');
    if (input.disabled) return;
    var max = parseInt(wrap.getAttribute('data-max'), 10) || 0;
    var n = parseInt(input.value, 10) || 0;
    if (btn.getAttribute('data-act') === 'plus') n++;
    else n--;
    if (n < 0) n = 0;
    if (n > max) n = max;
    input.value = String(n);
    recount();
  });

  form.addEventListener('input', function (e) {
    if (!e.target.classList.contains('prs-qty-input')) return;
    clamp(e.target);
    recount();
  });

  form.addEventListener('submit', function () {
    lanjut.disabled = true;
    lanjut.textContent = 'Memuat...';
  });

  search.addEventListener('input', function () {
    var term = search.value.trim().toLowerCase();
    document.querySelectorAll('.prs-rak-card').forEach(function (card) {
      var name = card.getAttribute('data-name') || '';
      card.style.display = !term || name.indexOf(term) !== -1 ? '' : 'none';
    });
  });

  recount();
})();
</script>
@endpush
