<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BankUnlinkedMail extends Mailable
{
    use Queueable, SerializesModels;

        public $user;

    /**
     * Create a new message instance.
     */
    public function __construct($user)
{
    $this->user = $user;
}

    public function build()
    {
        return $this->subject('Your Bank Account Has Been Unlinked')
            ->view('emails.bank-unlinked')
            ->with(['user' => $this->user]);
    }

}
