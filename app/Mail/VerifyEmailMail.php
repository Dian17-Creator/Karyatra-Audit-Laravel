<?php

namespace App\Mail;

use App\Models\Auth\Muser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public Muser $user;
    public string $token;
    public string $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Muser $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;

        $baseUrl = rtrim(config('app.url'), '/');
        $this->verificationUrl = $baseUrl . '/verify-email?token=' . rawurlencode($token);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifikasi email Auditra',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
            with: [
                'user'            => $this->user,
                'verificationUrl' => $this->verificationUrl,
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
