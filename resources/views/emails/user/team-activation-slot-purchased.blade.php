<x-mail::message>
    # Team Activation Slot Purchased!

    Hi {{ $userName }},

    Your payment of {{ $amountFormatted }} {{ $currency }} for a Team Activation Slot on {{ $appName }} was successful!

    You can now create one new independent team with premium features. This activation slot is valid until {{ $slotExpiresAt }}. Proceed to create your team in the app.

    Thanks,
    The {{ $appName }} Team
</x-mail::message>