<?php
namespace App\Mail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationCredentialsMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;
    public Organization $organization;
    public string $rawPassword;

    public function __construct(Organization $organization, string $rawPassword)
    {
        $this->organization = $organization;
        $this->rawPassword = $rawPassword;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->organization->email, // Send to Organization's contact email
            subject: 'Your New Organization Credentials for ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.organization-credentials',
            with: [
                'generatedPassword' => $this->rawPassword,
                'appName' => config('app.name'),
                'organizationName' => $this->organization->name,
                'organizationCode' => $this->organization->organization_code, // Still important
                'loginEmail' => $this->organization->email, // The username for login
                'panelLoginUrl' => url('/#org-panel-login-path'), // Placeholder for actual org panel login URL
            ],
        );
    }
    public function attachments(): array { return []; }
}