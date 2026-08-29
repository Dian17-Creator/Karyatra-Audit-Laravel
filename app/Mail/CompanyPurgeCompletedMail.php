<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyPurgeCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $ownerName;
    public string $companyName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $ownerName, string $companyName)
    {
        $this->ownerName = $ownerName;
        $this->companyName = $companyName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Data perusahaan Auditra telah dihapus permanen',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.company-purge-completed',
            with: [
                'ownerName'   => $this->ownerName,
                'companyName' => $this->companyName,
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
