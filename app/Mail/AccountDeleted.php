<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountDeleted extends Mailable
{
    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your MealMosaic account has been deleted');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.account-deleted');
    }
}
