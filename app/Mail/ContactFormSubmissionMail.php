<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmissionMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;
    public array $formData;
    public function __construct(array $formData) { $this->formData = $formData; }

    public function envelope(): Envelope {
        return new Envelope(
        // Reply-To header is important so you can reply directly to the user
            replyTo: $this->formData['email'],
            subject: 'New Contact Form Submission: ' . $this->formData['subject'],
        );
    }

    public function content(): Content {
        return new Content(
            markdown: 'emails.contact.submission',
            with: [ 'formData' => $this->formData,
                'appName' => config('app.name') ]
        );
    }
    public function attachments(): array { return []; }
}