<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\BmnSewaPengajuan;
use App\Models\BMN\PeminjamanAsetBmn;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BmnDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $totalAset = AsetBmn::query()->count();
        $asetAktif = AsetBmn::query()->where('status', 'aktif')->count();
        $asetDipinjam = AsetBmn::query()->where('status', 'dipinjam')->count();
        $asetRusak = AsetBmn::query()->whereIn('kondisi', ['rusak_ringan', 'rusak_berat'])->count();

        $pendingPeminjaman = PeminjamanAsetBmn::query()->where('status', 'pending')->count();
        $activePeminjaman = PeminjamanAsetBmn::query()->where('status', 'dipinjam')->count();
        $pendingSewa = BmnSewaPengajuan::query()->where('payment_status', 'pending')->count();

        $recentAset = AsetBmn::query()
            ->with(['kategori:id,nama_kategori', 'lokasiMaster:id,nama_lokasi'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentPeminjaman = PeminjamanAsetBmn::query()
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'kode_peminjaman', 'status', 'tanggal_pinjam', 'public_nama', 'user_id']);

        return view('bmn.dashboard.index', compact(
            'totalAset',
            'asetAktif',
            'asetDipinjam',
            'asetRusak',
            'pendingPeminjaman',
            'activePeminjaman',
            'pendingSewa',
            'recentAset',
            'recentPeminjaman'
        ));
    }
}
