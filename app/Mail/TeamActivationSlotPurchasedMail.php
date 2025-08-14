<?php
namespace App\Mail;
use App\Models\User; use App\Models\Payment; use Carbon\Carbon;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable; use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope; use Illuminate\Queue\SerializesModels;

class TeamActivationSlotPurchasedMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;
    public User $user; public Payment $payment; public Carbon $slotExpiresAt;

    public function __construct(User $user, Payment $payment, Carbon $slotExpiresAt) {
        $this->user = $user; $this->payment = $payment; $this->slotExpiresAt = $slotExpiresAt;
    }
    public function envelope(): Envelope {
        return new Envelope(subject: 'Team Activation Slot Purchased on ' . config('app.name'));
    }
    public function content(): Content {
        return new Content(markdown: 'emails.user.team-activation-slot-purchased', with: [
            'userName' => $this->user->first_name, 'appName' => config('app.name'),
            'amountFormatted' => number_format($this->payment->amount, 2), // Assumes amount is dollars from accessor
            'currency' => strtoupper($this->payment->currency),
            'slotExpiresAt' => $this->slotExpiresAt->toFormattedDayDateString(),
        ]);
    }
    public function attachments(): array { return []; }
}