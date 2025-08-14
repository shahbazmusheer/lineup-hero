<x-mail::message>
    # Subscription Activated!

    Hi {{ $userName }},

    Your payment of ${{ $payment->amount }} {{ $currency }} for {{ $appName }} has been successfully processed!

    Your account now has premium access, allowing you to generate PDF lineups for all your teams.
    This access is valid until {{ $expiresAt }}.

    Your Organization Access Code is:
        {{ $organizationAccessCode }}

    You might need this code for certain features or if prompted by the application.

    Thank you for subscribing!

    Best regards,
    The {{ $appName }} Team
</x-mail::message>
