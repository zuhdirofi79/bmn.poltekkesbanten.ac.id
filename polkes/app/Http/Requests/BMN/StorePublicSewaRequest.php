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
            // Nomor WhatsApp wajib. Format Indonesia (08/62/+62) — divalidasi
            // setelah dinormalisasi ke "62xxxxxxxx" oleh prepareForValidation.
            //
            // WhatsApp OTP sementara dinonaktifkan; nomor hanya divalidasi
            // formatnya untuk keperluan kontak admin.
            'nomor_wa' => [
                'required',
                'string',
                'max:30',
                'regex:/^62[0-9]{8,14}$/',
            ],
            'email' => ['required', 'email', 'max:255'],
            'tarif_id' => ['required', 'integer', 'exists:bmn_sewa_tarifs,id'],
            'tanggal_mulai_sewa' => ['required', 'date'],
            'tanggal_rencana_kembali' => ['required', 'date', 'after_or_equal:tanggal_mulai_sewa'],
            'tujuan_peminjaman' => ['required', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'surat_resmi' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'bukti_bayar' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor_wa.regex' => 'Nomor WhatsApp tidak valid. Gunakan format Indonesia: 08xxxxxxx, 62xxxxxxx, atau +62xxxxxxx.',
            'surat_resmi.max' => 'Ukuran surat resmi maksimal 10 MB.',
            'surat_resmi.mimes' => 'Surat resmi harus berformat PDF, DOC, DOCX, JPG, JPEG, atau PNG.',
            'bukti_bayar.max' => 'Ukuran bukti bayar maksimal 10 MB.',
            'bukti_bayar.mimes' => 'Bukti bayar harus berformat JPG, JPEG, PNG, atau PDF.',
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
