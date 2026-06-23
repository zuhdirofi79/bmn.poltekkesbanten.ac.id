<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            // Nomor WhatsApp wajib. Format Indonesia diterima dalam bentuk
            // 08xxxxxxx, 62xxxxxxx, atau +62xxxxxxx. `prepareForValidation`
            // sudah menormalisasi ke "62..." (digit saja) sehingga regex
            // berikut memeriksa hasil normalisasi tersebut (10-16 digit total).
            //
            // WhatsApp OTP sementara dinonaktifkan — nomor hanya divalidasi
            // formatnya, tidak diverifikasi via OTP.
            'public_no_wa' => [
                'required',
                'string',
                'max:30',
                'regex:/^62[0-9]{8,14}$/',
            ],
            'public_email' => ['required', 'email', 'max:255'],
            'requested_barang' => ['required', 'string', 'max:5000'],
            'tujuan_peminjaman' => ['required', 'string', 'max:255'],
            'tanggal_pinjam' => ['required', 'date'],
            'rencana_kembali' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'nota_dinas_files' => ['nullable', 'array', 'max:5'],
            'nota_dinas_files.*' => ['file', 'mimes:pdf', 'max:5120'],
            'nota_dinas' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasMulti = $this->hasFile('nota_dinas_files')
                && count(array_filter($this->file('nota_dinas_files', []))) > 0;
            $hasSingle = $this->hasFile('nota_dinas');

            if (! $hasMulti && ! $hasSingle) {
                $validator->errors()->add(
                    'nota_dinas_files',
                    'Minimal satu file Nota Dinas / Surat Permohonan wajib diunggah.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'public_no_wa.regex' => 'Nomor WhatsApp tidak valid. Gunakan format Indonesia: 08xxxxxxx, 62xxxxxxx, atau +62xxxxxxx.',
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
