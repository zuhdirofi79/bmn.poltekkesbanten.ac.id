<div class="dropdown-menu dropdown-menu-end dropdown-menu-right shadow border-0" aria-labelledby="bhpNotifDropdown" style="min-width: 320px; max-width: 380px;">
    <div class="px-3 py-2 border-bottom bg-light">
        <h6 class="mb-0 fw-semibold">Notifikasi</h6>
    </div>
    <div class="p-0" style="max-height: 360px; overflow-y: auto;">
        @if(empty($bhp_notifications) || !$bhp_notifications->count())
            <div class="px-3 py-4 text-center text-muted">
                Tidak ada notifikasi
            </div>
        @else
            @if(!empty($bhp_expired) && $bhp_expired->isNotEmpty())
                <div class="px-3 pt-2 pb-1 small text-uppercase text-muted fw-semibold">Sudah Expired</div>
                @foreach($bhp_expired as $item)
                    <div class="bhp-notif-item border-start border-3 border-danger bg-white px-3 py-2 mx-0" style="border-radius: 0 6px 6px 0;">
                        <div class="fw-bold text-dark">{{ $item['nama'] }}</div>
                        @if(!empty($item['merk']) && $item['merk'] !== '-')
                            <div class="small text-muted">{{ $item['merk'] }}</div>
                        @endif
                        <div class="small">Expired: {{ $item['tgl_expired'] }} · Stok: {{ $item['stok'] ?? 0 }}</div>
                        <a href="{{ route('petugas.laporan.bahan_bhp', ['jurusan' => $item['jurusan'], 'status_expired' => 'expired']) }}" class="btn btn-sm btn-outline-danger mt-1">Klik untuk detail</a>
                    </div>
                @endforeach
            @endif
            @if(!empty($bhp_upcoming) && $bhp_upcoming->isNotEmpty())
                <div class="px-3 pt-3 pb-1 small text-uppercase text-muted fw-semibold">Akan Expired (30 hari)</div>
                @foreach($bhp_upcoming as $item)
                    <div class="bhp-notif-item border-start border-3 border-warning bg-white px-3 py-2 mx-0" style="border-radius: 0 6px 6px 0;">
                        <div class="fw-bold text-dark">{{ $item['nama'] }}</div>
                        @if(!empty($item['merk']) && $item['merk'] !== '-')
                            <div class="small text-muted">{{ $item['merk'] }}</div>
                        @endif
                        <div class="small">Expired: {{ $item['tgl_expired'] }} · Sisa {{ $item['sisa_hari'] }} hari · Stok: {{ $item['stok'] ?? 0 }}</div>
                        <a href="{{ route('petugas.laporan.bahan_bhp', ['jurusan' => $item['jurusan'], 'status_expired' => 'not_expired']) }}" class="btn btn-sm btn-outline-warning mt-1">Klik untuk detail</a>
                    </div>
                @endforeach
            @endif
        @endif
    </div>
</div>
<style>
.bhp-notif-item { transition: background-color 0.15s ease; }
.bhp-notif-item:hover { background-color: #f8f9fa !important; }
</style>
