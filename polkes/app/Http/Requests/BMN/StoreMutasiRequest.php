<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use App\Models\BMN\AsetBmn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMutasiRequest extends FormRequest
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
            'aset_id' => ['required', Rule::exists('aset_bmn', 'id')->whereNull('deleted_at')],
            'ke_lokasi_id' => ['required', 'exists:lokasi_aset,id'],
            'tanggal_mutasi' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $asetId = (int) $this->input('aset_id');
            $keId = (int) $this->input('ke_lokasi_id');
            if ($asetId !== 0 && $keId !== 0) {
                $aset = AsetBmn::query()->find($asetId);
                if ($aset && (int) $aset->lokasi_id === $keId) {
                    $validator->errors()->add('ke_lokasi_id', 'Lokasi tujuan harus berbeda dari lokasi saat ini.');
                }
            }
        });
    }
}
