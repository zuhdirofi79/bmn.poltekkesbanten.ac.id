<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class EmailNotification extends Notification
{
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Peminjaman Alat')
            ->markdown('emails.notifikasi', ['url' => url('/'), 'data' => $this->data]);
    }

    public function via($notifiable)
    {
        return ['mail'];
    }
}
