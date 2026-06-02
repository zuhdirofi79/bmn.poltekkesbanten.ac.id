<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi input OTP untuk verifikasi pengajuan publik BMN.
 *
 * WhatsApp OTP sementara dinonaktifkan karena integrasi WhatsApp Business
 * API belum aktif/berbayar. Hanya `email_otp` yang divalidasi. Field
 * `wa_otp` legacy diterima sebagai nullable demi kompatibilitas mundur
 * dengan client / form lama, namun nilainya diabaikan.
 */
class VerifyPublicOtpRequest extends FormRequest
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
            'email_otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'wa_otp' => ['nullable', 'string', 'max:10'],
        ];
    }
}
