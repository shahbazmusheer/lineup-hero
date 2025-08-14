<x-mail::message>
    # Organization Subscription Renewed!

    Hi {{ $userName }},

    The subscription for your organization, {{ $organizationName }} (Code: `{{ $organizationCode }}`), on {{ $appName }} has been successfully renewed using the promo code: {{ $promoCodeUsed }}.

    Your organization's premium access is now extended until {{ $newExpiryDate }}.

    You can continue to manage your organization and its teams via the Organization Panel.

    Thank you for being a valued member!

    Best regards,
    The {{ $appName }} Team
</x-mail::message>