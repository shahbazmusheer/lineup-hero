<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\Organization;
// No need to import Team here if payments are only for User slots or Org subscriptions
use App\Models\UserTeamActivationSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // For logging

class AdminPaymentReceivedMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Payment $payment;
    public ?User $payingUser;      // User who made the payment (can be null if system initiated for an org)

    public ?Organization $payingOrganization;

    public ?Team $payingTeam;
    public $relatedEntity;       // Could be User (for slot), Organization, or null
    public string $paymentContext;  // e.g., "User Team Slot Purchase", "Organization Subscription Renewal"
    public string $entityType;      // 'user' or 'organization'

    /**
     * Create a new message instance.
     * @param Payment $payment
     * @param string $type Indicates if the payment primarily relates to a 'user' (e.g., for a slot)
     *                     or an 'organization' (e.g., org subscription/renewal).
     * @param User|null $initiatingUser The user model instance who initiated the payment, if applicable.
     */
    public function __construct(Payment $payment, string $type = 'user')
    {
        $this->payment = $payment;
        $this->entityType = strtolower($type);
        $this->payingUser = $initiatingUser ?? $payment->user; // Prioritize passed user, fallback to payment's user

        $this->payingOrganization = new Organization(['name' => 'System']);
        // Determine the primary related entity and payment context
        if ($this->entityType === 'team' && $payment->payable_type === Team::class) {
            $this->payment->loadMissing('payable'); // payable is Team
            $this->relatedEntity = $payment->payable;
            $this->payingTeam = $payment->payable;
            $this->payingUser = $this->payingTeam->user;
            $this->paymentContext = "Team Activation Renewal for '{$this->relatedEntity?->name}'";

        }elseif ($this->entityType === 'organization' && $payment->payable_type === Organization::class) {
            $this->payment->loadMissing('payable'); // payable is Organization
            $this->relatedEntity = $payment->payable;
            $this->payingOrganization = $payment->payable;
            $this->paymentContext = "Organization Subscription for '{$this->relatedEntity?->name}'";

        } elseif ($this->entityType === 'user' && $payment->payable_type === UserTeamActivationSlot::class) {
            $this->payment->loadMissing('payable.user'); // payable is UserTeamActivationSlot, then its user
            $this->relatedEntity = $payment->payable; // The slot itself
            $this->paymentContext = "Team Activation Slot Purchase by User '{$this->payingUser?->email}'";
            // payingUser should already be set correctly from payment->user
        } elseif ($this->entityType === 'user' && $payment->payable_type === Team::class) { // Direct Team Activation
            $this->payment->loadMissing('payable.user'); // payable is Team, then its user
            $this->relatedEntity = $payment->payable; // The Team
            $this->paymentContext = "Direct Activation for Team '{$this->relatedEntity?->name}' by User '{$this->payingUser?->email}'";
        }
        else {
            // Fallback or if payable_type is User (e.g. general user credit)
            $this->relatedEntity = $this->payingUser;
            $this->paymentContext = "Payment by User '{$this->payingUser?->email}'";
        }

        // Ensure payingUser is at least a dummy object if null, for the Blade view
        if (!$this->payingUser) {
            $this->payingUser = new User(['first_name' => 'System/Unknown', 'last_name' => 'Payer', 'email' => 'N/A']);
        }

    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Notification: ' . $this->paymentContext . ' - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        // The Payment model's 'amount' accessor should return dollars
        $amountDisplay = $this->payment->amount;

        return new Content(
            markdown: 'emails.admin.payment-received',
            with: [
                'payment' => $this->payment,
                'payingUser' => $this->payingUser,
                'payingOrganization' => $this->payingOrganization,
                'relatedEntity' => $this->relatedEntity,
                'paymentContextString' => $this->paymentContext, // More descriptive
                'entityType' => $this->entityType, // 'user' or 'organization'
                'appName' => config('app.name'),
                'amountFormatted' => is_numeric($amountDisplay) ? number_format((float)$amountDisplay, 2) : $amountDisplay,
                'currency' => strtoupper($this->payment->currency),
            ],
        );
    }

    public function attachments(): array { return []; }
}