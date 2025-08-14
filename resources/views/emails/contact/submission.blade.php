<x-mail::message>
    # New Contact Form Submission

    You have received a new message from the contact form on {{ config('app.name') }}.

    Name: {{ $formData['name'] }}
    Email: {{ $formData['email'] }}
    Subject: {{ $formData['subject'] }}

    ---

    Message:

    {{ $formData['message'] }}

    Thanks,
    {{ $appName }} System
</x-mail::message>