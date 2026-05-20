<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\PeminjamanAsetBmn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi pengajuan peminjaman aset BMN.
 *
 * Aturan bisnis:
 *   - hanya user yang boleh request (role=jurusan ATAU jurusan ada di whitelist)
 *   - minimal 1 aset, semua aset_id unik
 *   - aset wajib status = 'aktif' DAN tidak sedang memegang lock peminjaman lain
 *   - rencana_kembali >= tanggal_pinjam
 */
class StorePeminjamanAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canRequestBmn();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tujuan_peminjaman' => ['required', 'string', 'max:255'],
            'tanggal_pinjam' => ['required', 'date'],
            'rencana_kembali' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
            'catatan' => ['nullable', 'string', 'max:2000'],

            // Nota Dinas pendukung peminjaman. Nullable agar transaksi lama/manual
            // tetap bisa diproses; UI menampilkannya sebagai field opsional.
            // File disimpan langsung ke public/uploads/nota_dinas (lihat controller),
            // jadi mimes diperluas: PDF, dokumen Word, atau scan gambar.
            'nota_dinas' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:20480'],

            'aset_ids' => ['required', 'array', 'min:1', 'max:200'],
            'aset_ids.*' => ['integer', 'distinct', 'exists:aset_bmn,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota_dinas.file' => 'Nota dinas harus berupa file.',
            'nota_dinas.mimes' => 'Nota dinas harus berformat PDF, DOC, DOCX, JPG, JPEG, atau PNG.',
            'nota_dinas.max' => 'Ukuran nota dinas maksimal 20 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var array<int, mixed> $idsRaw */
            $idsRaw = (array) $this->input('aset_ids', []);
            $ids = array_values(array_unique(array_filter(array_map('intval', $idsRaw))));

            if ($ids === []) {
                return;
            }

            // 1. Pastikan semua aset berstatus aktif & tidak soft-deleted.
            $aktif = AsetBmn::query()
                ->whereIn('id', $ids)
                ->where('status', 'aktif')
                ->pluck('id')
                ->all();

            $tidakAktif = array_diff($ids, $aktif);
            if ($tidakAktif !== []) {
                $validator->errors()->add(
                    'aset_ids',
                    'Beberapa aset tidak tersedia (bukan status aktif / sudah dihapus): '
                        .implode(', ', $tidakAktif)
                );
            }

            // 2. Cegah double pinjam: aset tidak boleh terikat pada peminjaman
            //    pending/approved/dipinjam lain (lock aktif).
            //    Gunakan FK utama: peminjaman_aset_bmn_id & aset_bmn_id.
            $terikat = PeminjamanAsetBmn::query()
                ->join('peminjaman_aset_bmn_detail as d', 'd.peminjaman_aset_bmn_id', '=', 'peminjaman_aset_bmn.id')
                ->whereIn('d.aset_bmn_id', $ids)
                ->whereIn('peminjaman_aset_bmn.status', [
                    PeminjamanAsetBmn::STATUS_PENDING,
                    PeminjamanAsetBmn::STATUS_APPROVED,
                    PeminjamanAsetBmn::STATUS_DIPINJAM,
                ])
                ->pluck('d.aset_bmn_id')
                ->unique()
                ->all();

            if ($terikat !== []) {
                $validator->errors()->add(
                    'aset_ids',
                    'Aset berikut sedang berada dalam transaksi peminjaman lain: '
                        .implode(', ', $terikat)
                );
            }
        });
    }
}
