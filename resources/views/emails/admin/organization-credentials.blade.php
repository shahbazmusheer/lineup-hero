<x-mail::message>
    # Your Organization is Ready on {{ $appName }}!

    Hello,

    A new organization, {{ $organizationName }}, has been created for you on {{ $appName }}.
    You can use the following credentials to access the Organization Panel to manage your teams and subscription:

    Login details for the Organization Panel:
    Organization Email: {{ $loginEmail  }}
    Temporary Password: {{ $generatedPassword }}

    It is recommended to change this temporary password upon your first login.

    Your unique Organization Code (for coaches to link their teams) is: {{ $organizationCode }}
    If you did not request this or have questions, please contact our support.

    Thanks,
    The {{ $appName }} Team
</x-mail::message>