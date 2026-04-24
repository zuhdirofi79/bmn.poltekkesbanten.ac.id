<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use App\Models\BMN\AsetBmn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $aset = $this->route('aset');
        $asetId = $aset instanceof AsetBmn ? $aset->id : (int) $aset;

        return [
            'kode_barang' => [
                'required',
                'string',
                'max:255',
                Rule::unique('aset_bmn', 'kode_barang')->ignore($asetId),
            ],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'exists:kategori_aset,id'],
            'nilai_perolehan' => ['required', 'numeric', 'min:0'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'kondisi' => ['required', Rule::in(['baik', 'rusak_ringan', 'rusak_berat'])],
            'satker_id' => ['required', 'exists:bmn_satker,id'],
            'pengguna_ruangan' => ['nullable', 'string', 'max:200'],
            'nup' => ['nullable', 'string', 'max:50'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'dihapus', 'dipindahtangankan'])],
            'asset_tag_hash' => ['nullable', 'string', 'max:191'],
            'asset_tag_type' => ['nullable', Rule::in(['qr', 'barcode', 'rfid', 'manual'])],
            'foto_url' => ['nullable', 'url', 'max:2048'],
            'foto_preview_url' => ['nullable', 'url', 'max:2048'],
            'foto_provider' => ['nullable', Rule::in(['local', 's3', 'gdrive', 'other'])],
        ];
    }
}
