<?php

namespace App\Mail;

use App\Models\User;   // To get user's email and name
use App\Models\Team;   // To get team name
use Stripe\PaymentIntent; // To pass PaymentIntent for details
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserPaymentFailedMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public Team $team;
    public PaymentIntent $paymentIntent; // Store the Stripe PaymentIntent object
    public ?string $failureMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, ?Team $team, PaymentIntent $paymentIntent)
    {
        $this->user = $user;
        $this->team = $team;
        $this->paymentIntent = $paymentIntent;
        $this->failureMessage = $this->paymentIntent->last_payment_error->message ?? 'Your payment could not be processed.';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Payment Issue for ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content {
        return new Content(markdown: 'emails.user.payment-failed', with: [
            'userName' => $this->user->first_name, 'appName' => config('app.name'),
            'targetName' => $this->team ? "team \"{$this->team->name}\"" : "your account activation", // Dynamic target
            'amountFormatted' => number_format($this->paymentIntent->amount / 100, 2),
            'currency' => strtoupper($this->paymentIntent->currency),
            'failureMessage' => $this->failureMessage,
        ]);
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
