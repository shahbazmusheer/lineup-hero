<x-mail::message>
    # Team "{{ $teamName }}" Activated!

    Hi {{ $userName }},

    Great news! Your team, "{{ $teamName }}", has been successfully activated on {{ $appName }}.

    Premium features for this team are now available until {{ $expiresAt }}.

    You can manage your team and create lineups within the app.

    Happy coaching!

    Thanks,
    The {{ $appName }} Team
</x-mail::message>