<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use App\Models\BMN\PeminjamanAsetBmn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi pengembalian aset BMN (login & publik).
 */
class StorePengembalianAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return true;
        }

        $isApprover = $user->hasRole('admin') && (int) ($user->can_approve_bmn ?? 0) === 1;
        if ($isApprover || $user->hasRole('admin')) {
            return true;
        }

        $peminjamanId = (int) $this->input('peminjaman_aset_bmn_id');
        if ($peminjamanId === 0) {
            return false;
        }

        $peminjaman = PeminjamanAsetBmn::query()->find($peminjamanId);
        if ($peminjaman === null) {
            return false;
        }

        return (int) $peminjaman->user_id === (int) $user->id;
    }

    public function isGuestSubmission(): bool
    {
        return $this->user() === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isGuestSubmission()) {
            return [
                'kode_peminjaman' => ['required', 'string', 'max:32'],
                'public_nama' => ['required', 'string', 'max:255'],
                'public_no_wa' => ['required', 'string', 'max:30'],
                'public_email' => ['required', 'email', 'max:255'],
                'tanggal_kembali' => ['required', 'date'],
                'catatan_pengembalian' => ['nullable', 'string', 'max:2000'],
                'bukti_foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ];
        }

        return [
            'peminjaman_aset_bmn_id' => ['required', 'integer', 'exists:peminjaman_aset_bmn,id'],
            'tanggal_kembali' => ['required', 'date'],
            'kondisi_kembali' => ['nullable', 'in:baik,rusak_ringan,rusak_berat'],
            'catatan_pengembalian' => ['nullable', 'string', 'max:2000'],
            'bukti_foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bukti_foto.image' => 'Bukti foto harus berupa gambar.',
            'bukti_foto.mimes' => 'Bukti foto harus JPG, JPEG, PNG, atau WEBP.',
            'bukti_foto.max' => 'Ukuran bukti foto maksimal 4 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->isGuestSubmission() && $this->has('public_no_wa')) {
            $this->merge([
                'public_no_wa' => \App\Services\BMN\BmnWaNumberNormalizer::normalize(
                    (string) $this->input('public_no_wa')
                ),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->isGuestSubmission()) {
                $this->validateGuestPeminjaman($validator);

                return;
            }

            $this->validateLoginPeminjaman($validator);
        });
    }

    private function validateGuestPeminjaman(Validator $validator): void
    {
        $kode = trim((string) $this->input('kode_peminjaman'));
        if ($kode === '') {
            return;
        }

        $peminjaman = PeminjamanAsetBmn::query()
            ->where('kode_peminjaman', $kode)
            ->first();

        if ($peminjaman === null) {
            $validator->errors()->add('kode_peminjaman', 'Kode peminjaman tidak ditemukan.');

            return;
        }

        if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_DIPINJAM) {
            $validator->errors()->add(
                'kode_peminjaman',
                'Pengembalian hanya bisa untuk peminjaman berstatus dipinjam.'
            );

            return;
        }

        if ($peminjaman->pengembalian()->exists()) {
            $validator->errors()->add('kode_peminjaman', 'Transaksi ini sudah pernah diajukan pengembalian.');
        }

        if ($peminjaman->tanggal_pinjam !== null) {
            $tglKembali = (string) $this->input('tanggal_kembali');
            if ($tglKembali !== '' && strtotime($tglKembali) < strtotime((string) $peminjaman->tanggal_pinjam)) {
                $validator->errors()->add(
                    'tanggal_kembali',
                    'Tanggal pengembalian tidak boleh sebelum tanggal peminjaman.'
                );
            }
        }
    }

    private function validateLoginPeminjaman(Validator $validator): void
    {
        $peminjamanId = (int) $this->input('peminjaman_aset_bmn_id');
        if ($peminjamanId === 0) {
            return;
        }

        $peminjaman = PeminjamanAsetBmn::query()->find($peminjamanId);
        if ($peminjaman === null) {
            return;
        }

        if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_DIPINJAM) {
            $validator->errors()->add(
                'peminjaman_aset_bmn_id',
                'Pengembalian hanya bisa diproses untuk peminjaman berstatus "dipinjam".'
            );
        }

        if ($peminjaman->tanggal_pinjam !== null) {
            $tglKembali = (string) $this->input('tanggal_kembali');
            if ($tglKembali !== '' && strtotime($tglKembali) < strtotime((string) $peminjaman->tanggal_pinjam)) {
                $validator->errors()->add(
                    'tanggal_kembali',
                    'Tanggal pengembalian tidak boleh sebelum tanggal peminjaman.'
                );
            }
        }
    }
}
