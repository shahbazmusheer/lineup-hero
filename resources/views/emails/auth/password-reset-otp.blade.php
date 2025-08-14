<x-mail::message>
    # Password Reset Request

    Hi {{ $userName }},

    You requested a password reset for your {{ $appName }} account.
    Your One-Time Password (OTP) is:

        {{ $otp }}

    This OTP will expire in 10 minutes. If you did not request a password reset, no further action is required.

    Thanks,
    The {{ $appName }} Team
</x-mail::message>