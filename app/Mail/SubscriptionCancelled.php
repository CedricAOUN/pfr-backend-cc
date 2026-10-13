<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscriptionCancelled extends Mailable
{
    public function __construct(public string $name, public CarbonInterface $endsAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your MealMosaic subscription has been cancelled');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.subscription-cancelled');
    }
}
