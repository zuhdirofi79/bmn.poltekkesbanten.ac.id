<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsetRequest extends FormRequest
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
        return [
            'kode_barang' => ['required', 'string', 'max:255'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'exists:kategori_aset,id'],
            'nilai_perolehan' => ['required', 'numeric', 'min:0'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'kondisi' => ['required', Rule::in(['baik', 'rusak_ringan', 'rusak_berat'])],
            'satker_id' => ['required', 'exists:bmn_satker,id'],
            'pengguna_ruangan' => ['nullable', 'string', 'max:200'],
            'nup' => ['nullable', 'string', 'max:50', Rule::unique('aset_bmn', 'nup')->whereNull('deleted_at')],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'array', 'max:2'],
            'foto.*' => ['file', 'image', 'max:2048'],
        ];
    }
}
