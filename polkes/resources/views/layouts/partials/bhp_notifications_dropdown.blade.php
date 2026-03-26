<div class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="bhpNotifDropdown" style="min-width: 320px; max-width: 380px; z-index: 9999; position: absolute;">
    <div class="px-3 py-2 border-bottom bg-light">
        <h6 class="mb-0 fw-semibold">Notifikasi</h6>
    </div>
    <div class="p-0" style="max-height: 360px; overflow-y: auto;">
        @if(!($bhp_notifications instanceof \Illuminate\Support\Collection) || $bhp_notifications->isEmpty())
            <div class="px-3 py-4 text-center text-muted">
                @if($bhp_notifications instanceof \Illuminate\Support\Collection && $bhp_notifications->isEmpty())
                    <div>Tidak ada notifikasi saat ini</div>
                @else
                    <div>Tidak ada notifikasi saat ini</div>
                @endif
            </div>
        @else
            @if($bhp_expired instanceof \Illuminate\Support\Collection && $bhp_expired->isNotEmpty())
                <div class="px-3 pt-2 pb-1 small text-uppercase text-muted fw-semibold">Sudah Expired</div>
                @forelse($bhp_expired as $item)
                    @if(!is_array($item)) @continue @endif
                    <div class="bhp-notif-item border-start border-3 border-danger bg-white px-3 py-2 mx-0" style="border-radius: 0 6px 6px 0;">
                        <div class="fw-bold text-dark">{{ $item['nama'] ?? 'N/A' }}</div>
                        @if(($item['is_stale'] ?? false))
                            <span class="badge bg-warning text-dark">cache</span>
                        @endif
                        @if(($item['cache_age'] ?? null) > 5)
                            <span class="badge bg-danger">data lama</span>
                        @endif
                        @if(!empty($item['merk'] ?? null) && ($item['merk'] ?? '-') !== '-')
                            <div class="small text-muted">{{ $item['merk'] ?? 'N/A' }}</div>
                        @endif
                        <div class="small">Expired: {{ $item['tgl_expired'] ?? 'N/A' }} · Stok: {{ $item['stok'] ?? 0 }}</div>
                        <a href="{{ isset($item['jurusan']) && $item['jurusan'] ? route('petugas.laporan.bahan_bhp', ['jurusan' => $item['jurusan'], 'status_expired' => 'expired']) : '#' }}" class="btn btn-sm btn-outline-danger mt-1">Klik untuk detail</a>
                    </div>
                @empty
                @endforelse
            @endif

            @if($bhp_upcoming instanceof \Illuminate\Support\Collection && $bhp_upcoming->isNotEmpty())
                <div class="px-3 pt-3 pb-1 small text-uppercase text-muted fw-semibold">Akan Expired (90 hari)</div>
                @forelse($bhp_upcoming as $item)
                    @if(!is_array($item)) @continue @endif
                    <div class="bhp-notif-item border-start border-3 border-warning bg-white px-3 py-2 mx-0" style="border-radius: 0 6px 6px 0;">
                        <div class="fw-bold text-dark">{{ $item['nama'] ?? 'N/A' }}</div>
                        @if(($item['is_stale'] ?? false))
                            <span class="badge bg-warning text-dark">cache</span>
                        @endif
                        @if(($item['cache_age'] ?? null) > 5)
                            <span class="badge bg-danger">data lama</span>
                        @endif
                        @if(!empty($item['merk'] ?? null) && ($item['merk'] ?? '-') !== '-')
                            <div class="small text-muted">{{ $item['merk'] ?? 'N/A' }}</div>
                        @endif
                        <div class="small">Expired: {{ $item['tgl_expired'] ?? 'N/A' }} · Sisa {{ $item['sisa_hari'] ?? 'N/A' }} hari · Stok: {{ $item['stok'] ?? 0 }}</div>
                        <a href="{{ isset($item['jurusan']) && $item['jurusan'] ? route('petugas.laporan.bahan_bhp', ['jurusan' => $item['jurusan'], 'status_expired' => 'not_expired']) : '#' }}" class="btn btn-sm btn-outline-warning mt-1">Klik untuk detail</a>
                    </div>
                @empty
                @endforelse
            @endif

        @endif
    </div>
</div>
<style>
.bhp-notif-item { transition: background-color 0.15s ease; }
.bhp-notif-item:hover { background-color: #f8f9fa !important; }
</style>
