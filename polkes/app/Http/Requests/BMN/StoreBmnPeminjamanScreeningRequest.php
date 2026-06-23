<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;

class StoreBmnPeminjamanScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canScreenBmnPeminjaman();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $result = (string) $this->input('screening_result', '');

        $rules = [
            'screening_result' => ['required', 'in:tersedia,tidak_tersedia,perlu_konfirmasi'],
            'screening_note' => ['nullable', 'string', 'max:2000'],
            'kondisi_foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        if ($result === 'tersedia') {
            $rules['aset_ids'] = ['required', 'array', 'min:1', 'max:50'];
            $rules['aset_ids.*'] = ['integer', 'distinct', 'exists:aset_bmn,id'];
            $rules['kondisi_catatan'] = ['nullable', 'string', 'max:2000'];
        }

        if ($result === 'tidak_tersedia') {
            $rules['screening_note'] = ['required', 'string', 'max:2000'];
        }

        return $rules;
    }
}
