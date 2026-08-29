<?php

namespace App\Mail;

use App\Models\Auth\Muser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyDeletionCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public Muser $owner;
    public bool $isInactive;

    /**
     * Create a new message instance.
     */
    public function __construct(Muser $owner, bool $isInactive = false)
    {
        $this->owner = $owner;
        $this->isInactive = $isInactive;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Penghapusan perusahaan Auditra dibatalkan',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.company-deletion-cancelled',
            with: [
                'owner'      => $this->owner,
                'isInactive' => $this->isInactive,
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
