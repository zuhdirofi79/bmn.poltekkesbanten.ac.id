<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BmnPublicOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nama,
        public readonly string $otp,
        public readonly string $channelLabel
    ) {
    }

    public function build(): self
    {
        return $this->subject('Kode Verifikasi Pengajuan BMN')
            ->view('emails.bmn_public_otp');
    }
}
