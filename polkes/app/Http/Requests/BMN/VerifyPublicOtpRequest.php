<?php

declare(strict_types=1);

namespace App\Http\Requests\BMN;

use Illuminate\Foundation\Http\FormRequest;

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
            'wa_otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }
}
