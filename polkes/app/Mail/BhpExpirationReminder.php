<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Synchronous email for BHP items nearing expiration.
 * Used with QUEUE_CONNECTION=sync (no queue workers).
 */
class BhpExpirationReminder extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array */
    public $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function build()
    {
        return $this->subject('Reminder BHP Mendekati Expired')
            ->view('emails.bhp_expiration_reminder');
    }
}
