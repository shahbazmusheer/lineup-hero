<x-mail::message>
    # Your {{ $appName }} Password Was Changed

    Hi {{ $userName }},

    This is a confirmation that the password for your {{ $appName }} account was changed on {{ $changedAt }}.

    If you did not make this change, please attempt to reset your password.

    If you made this change, you can safely ignore this email.

    Thanks,
    The {{ $appName }} Team
</x-mail::message>
