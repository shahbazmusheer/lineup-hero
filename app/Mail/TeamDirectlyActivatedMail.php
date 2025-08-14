<?php
namespace App\Mail;
use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamDirectlyActivatedMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;
    public Team $team;
    public User $user;

    public function __construct(Team $team, User $user)
    {
        $this->team = $team;
        $this->user = $user;
    }
    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->user->email,
            subject: "Team '{$this->team->name}' Activated on " . config('app.name') . '!',
        );
    }
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.team-activated',
            with: [
                'userName' => $this->user->first_name,
                'teamName' => $this->team->name,
                'appName' => config('app.name'),
                'expiresAt' => $this->team->direct_activation_expires_at?->toFormattedDayDateString(),
                // 'teamUrl' => url("/teams/{$this->team->id}"), // Example if you have web view for team
            ],
        );
    }
    public function attachments(): array { return []; }
}