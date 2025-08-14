<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User; // The creator/admin of the organization
use App\Models\PromoCode; // The promo code used
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationSubscriptionRenewedViaPromoMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $organization;
    public User $recipientUser; // User receiving the email (usually organization.creator)
    public PromoCode $promoCode;

    /**
     * Create a new message instance.
     */
    public function __construct(Organization $organization, User $recipientUser, PromoCode $promoCode)
    {
        $this->organization = $organization;
        $this->recipientUser = $recipientUser;
        $this->promoCode = $promoCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->recipientUser->email,
            subject: 'Organization Subscription Renewed with Promo Code on ' . config('app.name') . '!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization.subscription-renewed-promo',
            with: [
                'organizationName' => $this->organization->name,
                'organizationCode' => $this->organization->organization_code,
                'userName' => $this->recipientUser->first_name,
                'appName' => config('app.name'),
                'promoCodeUsed' => $this->promoCode->code,
                'newExpiryDate' => $this->organization->subscription_expires_at?->toFormattedDayDateString(),
                'panelLoginUrl' => url('/organization-panel/login'), // Example, adjust if needed
            ],
        );
    }

    public function attachments(): array { return []; }
}