<x-mail::message>
    # Team Activation Slot Granted!

    Hi {{ $userName }},

    You've successfully redeemed the promo code {{ $promoCodeUsed }} on {{ $appName }}!

    This grants you one Team Activation Slot, allowing you to create an independent team with premium features. This activation slot is valid until {{ $slotExpiresAt }}.

    Proceed to create your team in the app.

    Thanks,
    The {{ $appName }} Team
</x-mail::message>