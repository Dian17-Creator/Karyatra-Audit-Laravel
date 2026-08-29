<?php

namespace App\Mail;

use App\Models\Auth\Muser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyDeactivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Muser $owner;

    /**
     * Create a new message instance.
     */
    public function __construct(Muser $owner)
    {
        $this->owner = $owner;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Perusahaan Auditra dinonaktifkan',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.company-deactivated',
            with: [
                'owner' => $this->owner,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
