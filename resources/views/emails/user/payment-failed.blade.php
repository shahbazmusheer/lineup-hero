<x-mail::message>
    # Payment Issue for "{{ $appName }}"

    Hi {{ $userName }},

    We encountered an issue processing your payment of {{ $payment->amount }} {{ $currency }} on for {{ $targetName }} {{ $appName }}.

    Reason: {{ $failureMessage }}

    Please update your payment information or try again to ensure continued access to all features for your team.
    You can attempt to make the payment again through the app or website.

    If you believe this is an error or have any questions, please contact our support team.

    Thanks,
    The {{ $appName }} Team
</x-mail::message>
