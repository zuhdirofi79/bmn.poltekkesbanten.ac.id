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
            'kode_barang' => ['required', 'string', 'max:255'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'exists:kategori_aset,id'],
            'nilai_perolehan' => ['required', 'numeric', 'min:0'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'kondisi' => ['required', Rule::in(['baik', 'rusak_ringan', 'rusak_berat'])],
            'satker_id' => ['required', 'exists:bmn_satker,id'],
            'pengguna_ruangan' => ['nullable', 'string', 'max:200'],
            'nup' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('aset_bmn', 'nup')->ignore($asetId)->whereNull('deleted_at'),
            ],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'dipindahtangankan'])],
            'foto' => ['nullable', 'array', 'max:2'],
            'foto.*' => ['file', 'image', 'max:2048'],
        ];
    }
}
