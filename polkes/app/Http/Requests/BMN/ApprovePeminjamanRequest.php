<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi aksi approve/reject peminjaman aset BMN.
 *
 * Wajib admin AND can_approve_bmn = 1; selain itu authorize() menolak.
 */
class ApprovePeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canApproveBmn();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'aksi' => ['required', 'in:approve,reject'],
            'alasan_reject' => ['nullable', 'required_if:aksi,reject', 'string', 'max:1000'],
        ];
    }
}
