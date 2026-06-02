<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicSewaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_wa' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'tarif_id' => ['required', 'integer', 'exists:bmn_sewa_tarifs,id'],
            'tanggal_mulai_sewa' => ['required', 'date'],
            'tanggal_rencana_kembali' => ['required', 'date', 'after_or_equal:tanggal_mulai_sewa'],
            'tujuan_peminjaman' => ['required', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'surat_resmi' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'bukti_bayar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nomor_wa')) {
            $this->merge([
                'nomor_wa' => \App\Services\BMN\BmnWaNumberNormalizer::normalize(
                    (string) $this->input('nomor_wa')
                ),
            ]);
        }
    }
}
