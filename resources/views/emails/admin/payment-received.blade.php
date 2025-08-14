<x-mail::message>
    # New Payment Received on {{ $appName }}

    A payment has been successfully processed.

    Payment Details:
    - Payment ID (Stripe): `{{ $payment->stripe_payment_intent_id }}`
    - Amount: {{ $payment->amount }} {{ $currency }}
    - Date: {{ $payment->paid_at->toFormattedDayDateString() }} at {{ $payment->paid_at->toTimeString() }}
    
    @if(isset($entityType))
        @if($entityType == 'organization')

    For Organization:
        - Organization ID: {{ $payingOrganization->id }}
        - Organization Name: {{ $payingOrganization->name }}
        - Organization Code: {{ $payingOrganization->organization_code }}
        - Organization Email: {{ $payingOrganization->email }}
        @endif
        @if($entityType == 'user' || $entityType == 'team')

    Paid By User:
        - User ID: {{ $payingUser->id }}
        - Name: {{ $payingUser->first_name }} {{ $payingUser->last_name }}
        - Email: {{ $payingUser->email }}
        @endif
    @endif

    Please review the transaction in the Stripe dashboard if necessary.

    Thanks,
    {{ $appName }} System
</x-mail::message>