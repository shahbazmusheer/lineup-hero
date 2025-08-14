<x-mail::message>
    # Organization Subscription Renewed!

    Hi {{ $userName }} (Admin for "{{ $organizationName }}"),

    Great news! The annual subscription for your organization, {{ $organizationName }} (Code: `{{ $organizationCode }}`), on {{ $appName }} has been successfully renewed.

    Your payment of {{ $amountFormatted }} {{ $currency }} has been processed.

    Your organization's premium access is now extended until {{ $newExpiryDate }}.

    You can continue to manage your organization and its teams via the Organization Panel.

    Thank you for your continued support!

    Best regards,
    The {{ $appName }} Team
</x-mail::message>