<?php

namespace App\Mail;

use App\Models\Organization; // Import Organization model
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationPasswordResetOtpMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $otp;
    public Organization $organization; // Pass the organization model

    /**
     * Create a new message instance.
     */
    public function __construct(string $otp, Organization $organization)
    {
        $this->otp = $otp;
        $this->organization = $organization;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->organization->email, // Send to the Organization's contact email
            subject: config('app.name') . ' - Organization Panel Password Reset Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization.password-reset-otp',
            with: [
                'otp' => $this->otp,
                'organizationName' => $this->organization->name,
                'organizationCode' => $this->organization->organization_code,
                'appName' => config('app.name'),
            ],
        );
    }

    public function attachments(): array { return []; }
}