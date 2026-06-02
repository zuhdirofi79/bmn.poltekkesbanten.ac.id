<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\VerifyPublicOtpRequest;
use App\Models\BMN\BmnSewaPengajuan;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PengembalianAsetBmn;
use App\Services\BMN\BmnPublicVerificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Halaman + endpoint verifikasi publik pengajuan BMN (peminjaman / pengembalian
 * / sewa). Saat ini hanya memverifikasi EMAIL (WhatsApp OTP dinonaktifkan —
 * lihat BmnPublicVerificationService).
 */
class BmnPublicVerificationController extends Controller
{
    public function __construct(
        private readonly BmnPublicVerificationService $verificationService
    ) {
    }

    public function show(string $type, int $id, string $token): View
    {
        $record = $this->resolveRecord($type, $id);
        abort_unless($this->verificationService->validateToken($record, $token), 404);

        return view('bmn.public.verification', [
            'type' => $type,
            'record' => $record,
            'token' => $token,
            'successRedirectUrl' => $this->successRedirectUrl($type, $record),
        ]);
    }

    public function verify(VerifyPublicOtpRequest $request, string $type, int $id, string $token): RedirectResponse
    {
        $record = $this->resolveRecord($type, $id);
        abort_unless($this->verificationService->validateToken($record, $token), 404);

        $result = $this->verificationService->verifyEmailOtp(
            $record,
            (string) $request->validated()['email_otp']
        );

        if (! $result['success']) {
            return back()->withInput()->with('error', $result['message']);
        }

        $redirect = $this->successRedirectUrl($type, $record);
        if ($redirect !== null) {
            return redirect($redirect)->with('success', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Resolusi record berdasarkan type. Hanya record dengan source publik
     * (atau jenis sewa publik) yang boleh diakses lewat endpoint ini.
     */
    private function resolveRecord(string $type, int $id): Model
    {
        if ($type === 'peminjaman') {
            /** @var PeminjamanAsetBmn $record */
            $record = PeminjamanAsetBmn::query()->findOrFail($id);
            abort_unless($record->isPublicSource(), 404);

            return $record;
        }

        if ($type === 'pengembalian') {
            /** @var PengembalianAsetBmn $record */
            $record = PengembalianAsetBmn::query()->findOrFail($id);
            abort_unless($record->isPublicSource(), 404);

            return $record;
        }

        if ($type === 'sewa') {
            /** @var BmnSewaPengajuan $record */
            $record = BmnSewaPengajuan::query()->findOrFail($id);

            return $record;
        }

        abort(404);
    }

    /**
     * URL tujuan setelah verifikasi email berhasil. Untuk sewa, lanjut ke
     * halaman success publik. Untuk peminjaman/pengembalian, tidak ada
     * redirect khusus — tetap di halaman verifikasi (status berubah jadi
     * "verified" dan menampilkan banner sukses).
     */
    private function successRedirectUrl(string $type, Model $record): ?string
    {
        if ($type === 'sewa') {
            return route('public.sewa.success', $record->getKey());
        }

        return null;
    }
}
