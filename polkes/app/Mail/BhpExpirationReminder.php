<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BhpExpirationReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * List of BHP items nearing expiration.
     * Each item: ['nama' => string, 'jurusan' => string, 'tgl_expired' => string, 'sisa_hari' => int]
     *
     * @var array
     */
    public $items;

    /**
     * Create a new message instance.
     *
     * @param array $items
     * @return void
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Reminder BHP Mendekati Expired')
            ->view('emails.bhp_expiration_reminder');
    }
}
