<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable  // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $changedAt; // Formatted timestamp of when password was changed

    /**
     * Create a new message instance.
     *
     * @param \App\Models\User $user
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->changedAt = now()->toFormattedDayDateString() . ' at ' . now()->toTimeString(); // Example format
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ' . config('app.name') . ' Password Has Been Changed',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.password-changed',
            with: [
                'userName' => $this->user->first_name,
                'appName' => config('app.name'),
                'changedAt' => $this->changedAt,
                // 'ipAddress' => request()->ip(), // Optional: Include IP if desired for security
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
