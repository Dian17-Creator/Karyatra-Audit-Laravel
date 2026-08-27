<?php

namespace App\Mail;

use App\Models\Subscription\Tsubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public Tsubscription $subscription;

    /**
     * Create a new message instance.
     */
    public function __construct(Tsubscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusText = $this->subscription->cstatus === 'approved' ? 'Disetujui' : 'Ditolak';
        return new Envelope(
            subject: "Keputusan Langganan Auditra Pro - {$statusText}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-decision',
            with: [
                'subscription' => $this->subscription,
                'owner'        => $this->subscription->owner,
                'plan'         => $this->subscription->plan,
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
