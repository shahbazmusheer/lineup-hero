<?php

namespace App\Mail;

use App\Models\User; // Import User model
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable // Implement ShouldQueue for background sending
{
    use Queueable, SerializesModels;

    public User $user; // Public property to pass user data to the view

    /**
     * Create a new message instance.
     *
     * @param \App\Models\User $user
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name') . '!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.welcome-user', // Path to the Markdown view
            with: [
                'userName' => $this->user->first_name, // Pass user's first name
                'appName' => config('app.name'),
                // You can add other data if needed, e.g., a link to login
                'loginUrl' => env('APP_URL')."/web/#/sign-in-screen", // If you have a named web login route
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
