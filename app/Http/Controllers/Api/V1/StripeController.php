<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Payment;
use App\Models\User;
use App\Models\Team;
use App\Models\UserTeamActivationSlot;
use App\Models\Organization;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Customer as StripeCustomer; // Alias Stripe Customer
use UnexpectedValueException;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminPaymentReceivedMail;
use App\Mail\TeamDirectlyActivatedMail; // For Path A team activation
use App\Mail\OrganizationSubscriptionRenewedMail; // For Org renewal
use App\Mail\UserPaymentFailedMail; // Generic, needs to handle context
use Carbon\Carbon;
use Illuminate\Support\Facades\URL; // For signed URLs
use Illuminate\Support\Facades\DB; // For transactions
use App\Mail\TeamActivationSlotPurchasedMail;


class StripeController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        Stripe::setApiVersion('2024-04-10'); // Or your preferred API version
    }

    /**
     * User gets a signed web link to RENEW a specific INDEPENDENT team's activation (Path A).
     * Route: GET /teams/{team}/generate-direct-renewal-link (User Auth)
     */
    public function generateDirectTeamRenewalLink(Request $request, Team $team)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if ($user->id !== $team->user_id) return $this->forbiddenResponse('You do not own this team.');
        if ($team->organization_id) return $this->errorResponse('This team is managed by an organization; renewal is via the organization.', \Illuminate\Http\Response::HTTP_BAD_REQUEST);

        // Optional: Add logic to prevent renewal if current activation is very far in the future
        // if ($team->direct_activation_status === 'active' && $team->direct_activation_expires_at && $team->direct_activation_expires_at->gt(now()->addMonths(11))) {
        //     return $this->errorResponse('This team\'s activation can be renewed closer to its expiry date.', \Illuminate\Http\Response::HTTP_CONFLICT);
        // }

        try {
            $signedUrl = URL::temporarySignedRoute(
                'team.payment.initiate.renewal', // Name of the new web route
                now()->addMinutes(30),
                ['team' => $team->id] // Pass team ID
            );
            return $this->successResponse(['payment_url' => $signedUrl], 'Secure payment link for team renewal generated.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate team renewal link for Team ID {$team->id}: " . $e->getMessage());
            return $this->errorResponse('Could not generate renewal link.', \Illuminate\Http\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generates a secure, temporary signed URL for the web-based new organization subscription payment page.
     * Route: GET /user/subscription/generate-payment-link
     */
    /**
     * User gets a signed web link to pay for a "Team Activation Slot" (Path A).
     * Route: GET /user/team-activation-slots/generate-payment-link
     */
    public function generateTeamActivationSlotWebLink(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        try {
            $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'team_activation_slot.payment.initiate.web', // New Web route name
                now()->addMinutes(30),
                ['user' => $user->id]
            );
            return $this->successResponse(['payment_url' => $signedUrl], 'Link for team activation slot payment generated.');
        }  catch (\Exception $e) {
            Log::error("Failed to generate signed payment URL for user {$user->id}: " . $e->getMessage());
            return $this->errorResponse(
                'Could not generate payment link. Please try again.',
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * User initiates payment for a "Team Activation Slot" (Path A).
     * Route: POST /user/team-activation-slots/create-payment-intent
     */
    public function createTeamActivationSlotPaymentIntent(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $settings = Settings::instance();
        $amountInDollars = (float) $settings->unlock_price_amount;
        $currency = $settings->unlock_currency;
        $amountInCents = (int) round($amountInDollars * 100);

        if (strtolower($currency) === 'usd' && $amountInCents < 50) {
            Log::error("Stripe PI for New Org Failed: User ID {$user->id}: Amount {$amountInCents} cents < minimum.");
            return $this->errorResponse('Subscription amount is below the minimum allowed.', HttpResponse::HTTP_BAD_REQUEST);
        }
        if (empty($currency)) {
            Log::error("Stripe PI for New Org Failed: User ID {$user->id}: Currency not set.");
            return $this->errorResponse('Payment configuration error (currency).', HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            if (!$user->stripe_customer_id) {
                $customer = StripeCustomer::create(['email' => $user->email, 'name' => $user->full_name, 'metadata' => ['app_user_id' => $user->id]]);
                $user->stripe_customer_id = $customer->id;
                $user->saveQuietly();
            }
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amountInCents, 'currency' => $currency,
                'customer' => $user->stripe_customer_id,
                'automatic_payment_methods' => ['enabled' => true],
                'description' => "Team Activation Slot Purchase by {$user->email}",
                'metadata' => [
                    'user_id' => $user->id,
                    'user_email' => $user->email, // For notifications
                    'action' => 'purchase_team_activation_slot' // For webhook
                ],
            ]);
            Log::info("Created Team Activation Slot PI {$paymentIntent->id} by User ID {$user->id}");

            return $this->successResponse([
                'clientSecret' => $paymentIntent->client_secret,
                'amount' => $amountInCents, 'currency' => $currency,
                'displayAmount' => number_format($amountInDollars, 2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displaySymbolPosition' => $settings->unlock_currency_symbol_position,
                'publishableKey' => config('services.stripe.key')
            ], 'Payment Intent for team activation created successfully.');

        } catch (ApiErrorException $e) {
            Log::error("Stripe Team Activation Slot PI API error: User {$user->id}: " . $e->getMessage(), ['stripe_error' => $e->getError()?->message]);
            return $this->errorResponse('Failed to initiate payment: ' . ($e->getError()?->message ?: 'Stripe API error.'), HttpResponse::HTTP_SERVICE_UNAVAILABLE);
        } catch (\Exception $e) {
            Log::error("Stripe Team Activation Slot PI creation failed: User {$user->id}: " . $e->getMessage());
            return $this->errorResponse('Failed to initiate payment process.', HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle incoming Stripe webhooks.
     */

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->server('HTTP_STRIPE_SIGNATURE');
        $webhookSecret = config('services.stripe.webhook_secret');
        if (!$webhookSecret) { return $this->errorResponse('Webhook secret not configured.', HttpResponse::HTTP_INTERNAL_SERVER_ERROR); }
        $event = null;
        try { $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret); }
        catch (\Exception $e) { return $this->errorResponse('Invalid webhook.', HttpResponse::HTTP_BAD_REQUEST); }

        Log::info('Stripe Webhook Received:', ['type' => $event->type, 'id' => $event->id]);
        $paymentIntent = $event->data->object;
        $action = $paymentIntent->metadata->action ?? null;

        switch ($event->type) {
            case 'payment_intent.succeeded':
                if ($action === 'purchase_team_activation_slot') {
                    $this->handleTeamActivationSlotPurchaseSucceeded($paymentIntent);
                    Log::info("Team Activation Slot Purchase Succeeded........");
                } elseif ($action === 'renew_team_direct') { // Specific renewal action
                    $this->handleDirectTeamActivationSucceeded($paymentIntent, true); // true for isRenewal
                } elseif ($action === 'renew_organization_subscription') {
                    $this->handleRenewOrganizationSubscriptionSucceeded($paymentIntent);
                } else {
                    Log::warning("Webhook PI Succeeded: Unknown action for PI {$paymentIntent->id}", ['metadata' => $paymentIntent->metadata]);
                }
                break;
            case 'payment_intent.payment_failed':
                if ($action === 'purchase_team_activation_slot') {
                    $this->handleTeamActivationSlotPurchaseFailed($paymentIntent);
                } elseif ($action === 'renew_organization_subscription') {
                    $this->handleOrganizationSubscriptionFailed($paymentIntent);
                } else {
                    Log::warning("Webhook PI Failed: Unknown action for PI {$paymentIntent->id}", ['metadata' => $paymentIntent->metadata]);
                }
                break;
            default: Log::info('Received unhandled Stripe event type: ' . $event->type);
        }
        return $this->successResponse(null, 'Webhook handled.');
    }

    // Modify handleDirectTeamActivationSucceeded to accept a renewal flag
    protected function handleDirectTeamActivationSucceeded(PaymentIntent $paymentIntent, bool $isRenewal = false): void
    {
        Log::info("Handling Direct Team Activation/Renewal Succeeded: PI {$paymentIntent->id}, Is Renewal: " . ($isRenewal ? 'Yes' : 'No'));
        $teamId = $paymentIntent->metadata->team_id ?? null;
        $userId = $paymentIntent->metadata->paying_user_id ?? null;

        if (!$teamId || !$userId) { Log::error("Webhook Team Activation Renew Error: Missing user_id or team_id for PI {$paymentIntent->id}"); return;  }
        if (Payment::where('stripe_payment_intent_id', $paymentIntent->id)->exists()) {  return; }

        $team = Team::find($teamId);
        $user = User::find($userId);

//        if (!$team || !$user || $team->user_id != $userId) { /* ... error log & return ... */ }
        if ($team->organization_id && !$isRenewal) { Log::error("Webhook Team Activation Renew Error: Team linked with an organization."); /* ... error log, this path is for independent teams ... */ return; }

        DB::beginTransaction();
        try {
            $payment = Payment::create([ /* ... payment data ... */
                'user_id' => $userId, 'payable_id' => $team->id, 'payable_type' => Team::class,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount_received, 'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status, 'paid_at' => now(),
            ]);

            $settings = Settings::instance();
            $durationDays = $settings->access_duration_days > 0 ? $settings->access_duration_days : 365;

            // For renewal, add to current future expiry if it exists, otherwise from now
            $newActivationExpiry = ($isRenewal && $team->direct_activation_expires_at && $team->direct_activation_expires_at->isFuture())
                ? $team->direct_activation_expires_at->addDays($durationDays)
                : Carbon::now()->addDays($durationDays);

            $team->activateDirectly($newActivationExpiry); // This method updates status and both expiry dates

            DB::commit();
            $logMessage = $isRenewal ? "Direct renewal" : "Direct activation";
            Log::info("{$logMessage} for Team ID {$teamId} by User ID {$userId} via PI {$paymentIntent->id}. Expires: {$team->direct_activation_expires_at->toIso8601String()}");

            if ($user->email && $user->receive_payment_notifications) {
                try {
                    // Consider a specific TeamDirectlyRenewedMail if message needs to be different
                    Mail::to($user->email)->send(new TeamDirectlyActivatedMail($team, $user));
                    Log::info("User notification sent for team " . ($isRenewal ? "renewal" : "activation") . " for Team ID {$teamId}");
                }
                catch (\Exception $e) { Log::error("Mail Error (TeamDirectActivate/Renew): {$e->getMessage()}");}
            }
            // ... Admin notification ...

            if ($settings->notify_admin_on_payment && !empty($settings->admin_notification_email)) {
                try { Mail::to($settings->admin_notification_email)->send(new AdminPaymentReceivedMail($payment, 'team')); } // Admin mail might need context
                catch (\Exception $e) { Log::error("Mail Error (AdminPayment SlotPurchase): {$e->getMessage()}");}
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::critical("CRITICAL: Failed to create team activation renewal: PI {$paymentIntent->id}, User {$userId}. Error: " . $e->getMessage());
        }
    }

    protected function handleTeamActivationSlotPurchaseSucceeded(PaymentIntent $paymentIntent): void
    {
        Log::info("Webhook: Handling Team Activation Slot Purchase Succeeded: PI {$paymentIntent->id}");
        $userId = $paymentIntent->metadata->paying_user_id ?? null;
        if (!$userId) { Log::error("Webhook SlotPurchase Error: Missing user_id for PI {$paymentIntent->id}"); return; }
        if (Payment::where('stripe_payment_intent_id', $paymentIntent->id)->exists()) { return; }

        $user = User::find($userId);
        if (!$user) { Log::error("Webhook SlotPurchase Error: User ID {$userId} not found from PI {$paymentIntent->id}"); return; }

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'user_id' => $userId,
                'payable_id' => $user->id, // Or null, or slot_id if linking payment to slot
                'payable_type' => User::class, // Or null, or UserTeamActivationSlot::class
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount_received, 'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status, 'paid_at' => now(),
            ]);

            $settings = Settings::instance();
            $durationDays = $settings->access_duration_days > 0 ? $settings->access_duration_days : 365;
            $slotExpiryDate = Carbon::now()->addDays($durationDays);

            $slot = UserTeamActivationSlot::create([
                'user_id' => $user->id, 'status' => 'available',
                'payment_id' => $payment->id, 'slot_expires_at' => $slotExpiryDate,
            ]);
            DB::commit();
            Log::info("Team Activation Slot ID {$slot->id} created for User ID {$userId} via PI {$paymentIntent->id}. Slot Expires: {$slotExpiryDate->toIso8601String()}");

            if ($user->email && $user->receive_payment_notifications) {
                try { Mail::to($user->email)->send(new TeamActivationSlotPurchasedMail($user, $payment, $slotExpiryDate)); }
                catch (\Exception $e) { Log::error("Mail Error (TeamActivationSlotPurchased): {$e->getMessage()}");}
            }
            if ($settings->notify_admin_on_payment && !empty($settings->admin_notification_email)) {
                try { Mail::to($settings->admin_notification_email)->send(new AdminPaymentReceivedMail($payment, 'user')); } // Admin mail might need context
                catch (\Exception $e) { Log::error("Mail Error (AdminPayment SlotPurchase): {$e->getMessage()}");}
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::critical("CRITICAL: Failed to create team activation slot: PI {$paymentIntent->id}, User {$userId}. Error: " . $e->getMessage());
        }
    }

    /**
     * Handle a failed payment intent for a team activation slot purchase.
     */
    protected function handleTeamActivationSlotPurchaseFailed(PaymentIntent $paymentIntent): void
    {
        Log::warning("Webhook: Handling Team Activation Slot Purchase Failed: PI {$paymentIntent->id}", ['metadata' => $paymentIntent->metadata]);
        $userId = $paymentIntent->metadata->user_id ?? null;
        $user = $userId ? User::find($userId) : null;

        if ($user && $user->email && $user->receive_payment_notifications) {
            try {
                // UserPaymentFailedMail now takes User, nullable Team (null here), and PaymentIntent
                Mail::to($user->email)->send(new UserPaymentFailedMail($user, null, $paymentIntent));
                Log::info("User notification sent for failed team activation slot purchase PI {$paymentIntent->id} to {$user->email}");
            } catch (\Exception $e) {
                Log::error("Mail Error (UserTeamActivationSlotFailed): {$e->getMessage()} for User ID {$userId}, PI {$paymentIntent->id}");
            }
        }
        // Optionally record the failed payment attempt in 'payments' table with 'failed' status
        if ($userId && !Payment::where('stripe_payment_intent_id', $paymentIntent->id)->exists()) {
            Payment::create([
                'user_id' => $userId,
                'payable_id' => $user->id, // Or null
                'payable_type' => User::class, // Or null
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'status' => 'failed', // Mark as failed
                'paid_at' => null,
            ]);
            Log::info("Recorded failed team activation slot payment attempt for PI {$paymentIntent->id}");
        }
    }
    protected function handleRenewOrganizationSubscriptionSucceeded(PaymentIntent $paymentIntent): void
    {
        Log::info("Webhook: Handling Organization Subscription Renewal Succeeded: PI {$paymentIntent->id}");
        $organizationId = $paymentIntent->metadata->organization_id ?? null;
        if (!$organizationId) {
            Log::error("Webhook Renew Error: Missing organization_id for PI {$paymentIntent->id}");
            return;
        }
        if (Payment::where('stripe_payment_intent_id', $paymentIntent->id)->exists()) {
            Log::info("Webhook Renew Info: PI {$paymentIntent->id} already processed.");
            return;
        }

        $organization = Organization::find($organizationId); // Eager load creator
        if (!$organization) {
            Log::error("Webhook Renew Error: Organization ID {$organizationId} not found from PI {$paymentIntent->id}");
            return;
        }

        // The user who *made* the payment (could be null if not tracked, or the org's creator by default)
        $payingUserId = $paymentIntent->metadata->paying_user_id ?? $organization->creator_user_id;

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'user_id' => $payingUserId, // User who initiated/paid for renewal
                'organization_id' => $organization->id,
                'payable_id' => $organization->id, // Polymorphic: payment is FOR the organization
                'payable_type' => Organization::class,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount_received,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
                'paid_at' => now(),
            ]);

            $settings = Settings::instance();
            $durationDays = $settings->access_duration_days > 0 ? $settings->access_duration_days : 365;
            $newExpiryDate = $organization->subscription_expires_at && $organization->subscription_expires_at->isFuture()
                ? $organization->subscription_expires_at->addDays($durationDays)
                : Carbon::now()->addDays($durationDays);

            $organization->subscription_status = 'active';
            $organization->subscription_expires_at = $newExpiryDate;
            $organization->stripe_subscription_id = $paymentIntent->id; // Reference to the activating PI/Sub
            // If this payment created a new Stripe Customer ID for the org, ensure it's saved (WebPaymentController should do this)
            if ($paymentIntent->customer && empty($organization->stripe_customer_id)) {
                $organization->stripe_customer_id = $paymentIntent->customer;
            }
            $organization->teams_created_this_period = 0; // Reset team allocation count
            $organization->save();
            DB::commit();

            Log::info("Org ID {$organization->id} subscription renewed. Initiated by User ID {$payingUserId}. New Expiry: {$newExpiryDate->toIso8601String()}");

            // --- Send Organization Renewal Success Email ---
            // Send to the Organization's contact email. The $payingUser for the mailable context is the org's creator.
            if ($organization->email) { // Check if organization has a contact email
                $recipientForOrgNotification = $organization->creator ?? new User(['email' => $organization->email, 'first_name' => $organization->name]); // Fallback if no creator user
                if ($recipientForOrgNotification->email) { // Final check for email
                    try {
                        Mail::to($organization->email)->send(new OrganizationSubscriptionRenewedMail($organization, $recipientForOrgNotification, Payment::find($payment->id) /* pass payment */));
                        Log::info("Organization subscription renewal success email sent to {$organization->email} for Org ID {$organization->id}");
                    } catch (\Exception $e) {
                        Log::error("Mail Error (OrgSubRenewed to Org Email): {$e->getMessage()}");
                    }
                }
            }

            // --- Send Admin Notification Email (if enabled) ---
            if ($settings->notify_admin_on_payment && !empty($settings->admin_notification_email)) {
                try {
                    Mail::to($settings->admin_notification_email)->send(new AdminPaymentReceivedMail($payment, 'organization')); // Pass the $payment object
                    Log::info("Admin payment notification sent for org renewal PI {$paymentIntent->id}");
                } catch (\Exception $e) {
                    Log::error("Mail Error (AdminPayment OrgRenewal): {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::critical("CRITICAL: Failed to renew organization subscription or payment record: PI {$paymentIntent->id}, Org {$organizationId}. Error: " . $e->getMessage(), ['exception' => $e]);
        }
    }

    protected function handleOrganizationSubscriptionFailed(PaymentIntent $paymentIntent): void {
        Log::warning("Org Subscription payment_intent.payment_failed: PI {$paymentIntent->id}", ['metadata' => $paymentIntent->metadata]);
        $creatorUserId = $paymentIntent->metadata->creator_user_id ?? ($paymentIntent->metadata->paying_user_id ?? null);
        $user = $creatorUserId ? User::find($creatorUserId) : null;

        if ($user && $user->email && $user->receive_payment_notifications) {
            try {
                // UserPaymentFailedMail's $team parameter should be nullable
                Mail::to($user->email)->send(new UserPaymentFailedMail($user, null, $paymentIntent));
            } catch (\Exception $e) { Log::error("Mail Error (UserPaymentFailed): {$e->getMessage()}"); }
        }
    }

    /**
     * Display the authenticated user's payment history.
     * Route: GET /payments/history
     */

    /**
     * Display the authenticated user's payment history.
     * Includes payments for team activation slots and any orgs they might have created/renewed (if that logic existed).
     */
    public function userPaymentHistory(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (!$user) return $this->unauthorizedResponse('User not authenticated.');

        $payments = Payment::where('user_id', $user->id)
            ->with([
                'payable' => function ($morphTo) {
                    $morphTo->morphWith([
                        Team::class => ['user:id,first_name', 'organization:id,name'],
                        Organization::class => ['creator:id,first_name'],
                        User::class => [], // If payable_type can be User (for slots)
                        UserTeamActivationSlot::class => [] // If linking payment directly to slot
                    ]);
                }
            ])
            ->orderBy('paid_at', 'desc')
            ->select([ /* ... select relevant fields ... */
                'id', 'payable_id', 'payable_type', 'stripe_payment_intent_id',
                'amount', 'currency', 'status', 'paid_at', 'created_at'
            ])
            ->paginate($request->input('per_page', 15));

        $payments->getCollection()->transform(function ($payment) {
            if ($payment->payable_type === User::class && $payment->payable_id === $payment->user_id) {
                $payment->activation_target_description = "Team Activation Slot Purchase";
            } elseif ($payment->payable_type === Team::class && $payment->payable) {
                $payment->activation_target_description = "Direct Activation for Team: {$payment->payable->name}";
            } elseif ($payment->payable_type === Organization::class && $payment->payable) {
                $payment->activation_target_description = "Subscription for Organization: {$payment->payable->name}";
            } else {
                $payment->activation_target_description = "General Payment";
            }
            // Amount accessor on Payment model handles dollar conversion
            return $payment;
        });
        return $this->successResponse($payments, 'Payment history retrieved successfully.');
    }

}