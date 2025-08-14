<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User; // The creator/paying user
use App\Models\Payment; // The payment record for this renewal
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationSubscriptionRenewedMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $organization;
    public User $payingUser; // User who made the renewal payment (usually the creator)
    public Payment $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Organization $organization, User $recipientUser, ?Payment $payment = null, ?PromoCode $promoCode = null)
    {
        $this->organization = $organization;
        $this->payingUser = $recipientUser;
        $this->payment = $payment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Send to the organization's contact email or its creator's email
        $recipientEmail = $this->organization->email ?? $this->organization->creator?->email;
        if (!$recipientEmail) {
            // Fallback or log error - should not happen if org has creator or email
            $recipientEmail = config('mail.from.address'); // Should not happen
        }

        return new Envelope(
            to: $recipientEmail,
            subject: 'Your ' . config('app.name') . ' Organization Subscription Has Been Renewed!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization.subscription-renewed',
            with: [
                'organizationName' => $this->organization->name,
                'organizationCode' => $this->organization->organization_code,
                'userName' => $this->payingUser->first_name, // Person who paid for renewal
                'appName' => config('app.name'),
                'newExpiryDate' => $this->organization->subscription_expires_at?->toFormattedDayDateString(),
                'amountFormatted' => $this->payment->amount, // Assuming payment->amount is dollars
                'currency' => strtoupper($this->payment->currency),
                'panelLoginUrl' => url('/organization-panel/login'), // Example
            ],
        );
    }

    public function attachments(): array { return []; }
}