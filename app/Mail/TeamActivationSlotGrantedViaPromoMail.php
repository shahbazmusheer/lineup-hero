<?php
namespace App\Mail;
use App\Models\User; use App\Models\PromoCode; use Carbon\Carbon;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable; use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope; use Illuminate\Queue\SerializesModels;

class TeamActivationSlotGrantedViaPromoMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;
    public User $user; public PromoCode $promoCode; public Carbon $slotExpiresAt;
    public function __construct(User $user, PromoCode $promoCode, Carbon $slotExpiresAt) {
        $this->user = $user; $this->promoCode = $promoCode; $this->slotExpiresAt = $slotExpiresAt;
    }
    public function envelope(): Envelope { return new Envelope(subject: 'Team Activation Slot Granted on ' . config('app.name')); }
    public function content(): Content {
        return new Content(markdown: 'emails.user.team-activation-slot-promo', with: [
            'userName' => $this->user->first_name, 'appName' => config('app.name'),
            'promoCodeUsed' => $this->promoCode->code,
            'slotExpiresAt' => $this->slotExpiresAt->toFormattedDayDateString(),
        ]);
    }
    public function attachments(): array { return []; }
}