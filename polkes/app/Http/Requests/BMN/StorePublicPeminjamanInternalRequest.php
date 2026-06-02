<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicPeminjamanInternalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'public_nama' => ['required', 'string', 'max:255'],
            'public_no_wa' => ['required', 'string', 'max:30'],
            'public_email' => ['required', 'email', 'max:255'],
            'requested_barang' => ['required', 'string', 'max:5000'],
            'tujuan_peminjaman' => ['required', 'string', 'max:255'],
            'tanggal_pinjam' => ['required', 'date'],
            'rencana_kembali' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'nota_dinas' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('public_no_wa')) {
            $this->merge([
                'public_no_wa' => \App\Services\BMN\BmnWaNumberNormalizer::normalize(
                    (string) $this->input('public_no_wa')
                ),
            ]);
        }
    }
}
