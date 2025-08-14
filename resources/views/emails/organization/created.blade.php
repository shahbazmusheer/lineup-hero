<x-mail::message>
    # Your Organization "{{ $organizationName }}" is Active!

    Hi {{ $creatorName }},

    Congratulations! Your new organization, {{ $organizationName }}, has been successfully created and activated on {{ $appName }}.

    You can now manage your organization and its teams using the Organization Panel.
    Here are your login details for the Organization Panel:

    Username (Organization Code): {{ $organizationCode }}
    Password: {{ $generatedPassword }}

    Access your organization panel using above login details. Your organization's subscription is active until {{ $subscriptionExpiresAt }}.

    You can share the Organization Code (`{{ $organizationCode }}`) with other coaches/users if they wish to create teams under this organization (they will still need their own user accounts).

    Thanks,
    The {{ $appName }} Team
</x-mail::message>