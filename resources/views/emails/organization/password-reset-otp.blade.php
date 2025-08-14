<x-mail::message>
    # Organization Panel Password Reset Request

    Hi {{ $organizationName }} Admin,

    A password reset was requested for your Organization Panel access on {{ $appName }} having Organization Code: {{ $organizationCode }}.
    Your One-Time Password (OTP) is:

        {{ $otp }}

    This OTP will expire in 10 minutes. If you did not request this password reset, please ignore this email.

    Thanks,
    The {{ $appName }} Team
</x-mail::message>