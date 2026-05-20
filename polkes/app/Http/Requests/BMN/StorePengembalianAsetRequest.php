<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use App\Models\BMN\PeminjamanAsetBmn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi pengembalian aset BMN.
 *
 * Diizinkan: approval officer ATAU admin biasa (yang menerima fisik).
 * Pengajuan hanya valid bila peminjaman bersangkutan dalam status "dipinjam".
 *
 * Catatan skema DB production:
 *   - FK utama ke header: peminjaman_aset_bmn_id (bukan peminjaman_id).
 *   - Kolom kondisi_kembali HANYA ada di tabel detail
 *     (peminjaman_aset_bmn_detail.kondisi_kembali), bukan di tabel pengembalian.
 */
class StorePengembalianAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        // Approver (admin + can_approve_bmn = 1) selalu boleh memproses.
        $isApprover = $user->hasRole('admin') && (int) ($user->can_approve_bmn ?? 0) === 1;
        if ($isApprover) {
            return true;
        }

        // Peminjam hanya boleh memproses pengembalian untuk transaksi miliknya.
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'peminjaman_aset_bmn_id' => ['required', 'integer', 'exists:peminjaman_aset_bmn,id'],
            'tanggal_kembali' => ['required', 'date'],
            'kondisi_kembali' => ['nullable', 'in:baik,rusak_ringan,rusak_berat'],
            'catatan_pengembalian' => ['nullable', 'string', 'max:2000'],

            // Foto bukti kondisi aset saat pengembalian (opsional).
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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

            // Tanggal kembali tidak boleh sebelum tanggal pinjam.
            if ($peminjaman->tanggal_pinjam !== null) {
                $tglKembali = (string) $this->input('tanggal_kembali');
                if ($tglKembali !== '' && strtotime($tglKembali) < strtotime((string) $peminjaman->tanggal_pinjam)) {
                    $validator->errors()->add(
                        'tanggal_kembali',
                        'Tanggal pengembalian tidak boleh sebelum tanggal peminjaman.'
                    );
                }
            }
        });
    }
}
