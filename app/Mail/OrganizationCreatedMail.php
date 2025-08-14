<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User; // The creator user
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationCreatedMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $organization;
    public string $rawPassword; // The generated password for the organization
    public User $creator;       // The user who created the organization

    /**
     * Create a new message instance.
     */
    public function __construct(Organization $organization, string $rawPassword, User $creator)
    {
        $this->organization = $organization;
        $this->rawPassword = $rawPassword;
        $this->creator = $creator;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->creator->email, // Send to the creator user's email
            subject: 'Your New Organization on ' . config('app.name') . ' is Ready!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization.created',
            with: [
                'creatorName' => $this->creator->first_name,
                'organizationName' => $this->organization->name,
                'organizationCode' => $this->organization->organization_code, // This is the username
                'generatedPassword' => $this->rawPassword,
                'appName' => config('app.name'),
                'loginUrl' => url('/organization-panel/login'), // Example URL, adjust as needed
                'subscriptionExpiresAt' => $this->organization->subscription_expires_at?->toFormattedDayDateString(),
            ],
        );
    }

    public function attachments(): array { return []; }
}