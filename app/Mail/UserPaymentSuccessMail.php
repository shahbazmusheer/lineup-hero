<?php
namespace App\Mail;

use App\Models\User;
use App\Models\Payment; // To pass payment details
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserPaymentSuccessMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;
    public User $user;
    public Payment $payment;

    public function __construct(User $user, Payment $payment)
    {
        $this->user = $user;
        $this->payment = $payment;
    }
    public function envelope(): Envelope {
        return new Envelope(subject: 'Your ' . config('app.name') . ' Subscription is Active!');
    }
    public function content(): Content {
        return new Content(
            markdown: 'emails.user.payment-success',
            with: [
                'userName' => $this->user->first_name,
                'appName' => config('app.name'),
                'organizationAccessCode' => $this->user->organization_access_code,
                'expiresAt' => $this->user->subscription_expires_at?->toFormattedDayDateString(),
                'amountFormatted' => number_format($this->payment->amount / 100, 2),
                'currency' => strtoupper($this->payment->currency),
            ]
        );
    }
    public function attachments(): array { return []; }
}