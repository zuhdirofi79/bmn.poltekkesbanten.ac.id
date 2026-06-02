<?php

declare(strict_types=1);

namespace App\Http\Controllers\SewaAdmin;

use App\Http\Controllers\Controller;
use App\Models\BMN\BmnSewaPengajuan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SewaAdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $now = Carbon::now();

        $base = BmnSewaPengajuan::query();

        $pengajuanMasuk = (clone $base)->count();
        $menungguPembayaran = (clone $base)->where('payment_status', BmnSewaPengajuan::PAYMENT_PENDING)->count();
        $terbayar = (clone $base)->where('payment_status', BmnSewaPengajuan::PAYMENT_CONFIRMED)->count();
        $perluKoordinasi = (clone $base)
            ->where('payment_status', BmnSewaPengajuan::PAYMENT_CONFIRMED)
            ->where('coordination_status', BmnSewaPengajuan::COORD_PENDING)
            ->count();
        $selesaiBulanIni = (clone $base)
            ->where('coordination_status', BmnSewaPengajuan::COORD_DONE)
            ->whereYear('coordination_at', $now->year)
            ->whereMonth('coordination_at', $now->month)
            ->count();
        $ditolakBulanIni = (clone $base)
            ->where('payment_status', BmnSewaPengajuan::PAYMENT_REJECTED)
            ->whereYear('admin_payment_at', $now->year)
            ->whereMonth('admin_payment_at', $now->month)
            ->count();

        $recent = (clone $base)
            ->with(['tarif:id,nama_layanan,kategori,satuan'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('sewa_admin.dashboard.index', [
            'pengajuanMasuk' => $pengajuanMasuk,
            'menungguPembayaran' => $menungguPembayaran,
            'terbayar' => $terbayar,
            'perluKoordinasi' => $perluKoordinasi,
            'selesaiBulanIni' => $selesaiBulanIni,
            'ditolakBulanIni' => $ditolakBulanIni,
            'recent' => $recent,
        ]);
    }
}
