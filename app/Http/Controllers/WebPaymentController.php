<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Team;
use App\Models\User;    // Import User model
use App\Models\Settings;
use http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Customer as StripeCustomer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse; // For return type hint

class WebPaymentController extends Controller
{
    public function __construct()
    {
        // NO AUTH MIDDLEWARE HERE as it's accessed via signed URL
        Stripe::setApiKey(config('services.stripe.secret'));
        Stripe::setApiVersion('2024-04-10'); // Use your desired API version
    }

    /**
     * Show payment page for a User to purchase a "Team Activation Slot" (Path A).
     * The {user} model is injected via route model binding from the signed URL.
     * The 'signed' middleware on the route validates the URL's integrity.
     * Route Name: 'team_activation_slot.payment.initiate.web'
     */
    public function showTeamActivationSlotPage(Request $request, User $user): View
    {

        $settings = Settings::instance();
        $amountInDollars = (float) $settings->unlock_price_amount;
        $currency = $settings->unlock_currency;
        $amountInCents = (int) round($amountInDollars * 100);
        // $user is the one paying for the slot, validated by the signed URL.
        // Optional: Check if user already has too many available slots.

        $retryUrl = $request->fullUrl();
        $paymentDescription = "Complete your payment to receive a slot for activating one team with premium features for " . ($settings->access_duration_days ?? 365) . " days.";

        $availableSlots = $user->teamActivationSlots()->where('status', 'available')->where('slot_expires_at', '>', now())->count();
        if ($availableSlots >= 15) { // Example limit
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'retryUrl' => $retryUrl,
                'user' => $user,'amount' => $amountInDollars, 'currency' => $currency,
                'pageTitle' => 'Limit Reached',
                'messageBody' => 'You have reached the maximum number of available team activation slots.'
            ]);
        }

        if (strtolower($currency) === 'usd' && $amountInCents < 50) {
            Log::error("Web Payment Error: Slot purchase amount {$amountInCents}c for User ID {$user->id} is below minimum.");
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user', 'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Payment Error',
                'messageBody' => 'The activation price is below the minimum allowed. Please contact support.'
            ]);
        }
        if (empty($currency)) {
            Log::error("Web Payment Error: Currency not set for slot purchase by User ID {$user->id}.");
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Configuration Error',
                'messageBody' => 'Payment currency is not configured. Please contact support.'
            ]);
        }

        try {
            if (!$user->stripe_customer_id) {
                $customer = StripeCustomer::create(['email' => $user->email, 'name' => $user->full_name, 'metadata' => ['app_user_id' => $user->id]]);
                $user->stripe_customer_id = $customer->id;
                $user->saveQuietly();
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'customer' => $user->stripe_customer_id,
                'automatic_payment_methods' => ['enabled' => true],
                'description' => "Team Activation Slot Purchase by {$user->email} ({$user->id})",
                'metadata' => [
                    'type' => 'user',
                    'paying_user_id' => $user->id, // User purchasing the slot
                    'user_email' => $user->email,
                    'action' => 'purchase_team_activation_slot', // For webhook processing
                    'payment_description' => $paymentDescription,
                    'successMsg'=>'Your Team Activation Slot is being processed and will be available shortly. Check your email for confirmation.'
                ],
            ]);
            Log::info("Web Flow: Created Team Activation Slot PI {$paymentIntent->id} for User ID {$user->id}");

            return view('payments.subscribe_page', [ // Using a generic payment page view
                'type' => 'user',
                'stripeKey' => config('services.stripe.key'),
                'clientSecret' => $paymentIntent->client_secret,
                'paymentTitle' => "Purchase Team Activation Slot",
                'paymentDescription' => $paymentDescription,
                'user' => $user, // For displaying "Welcome, [User Name]" in navbar
                'retryUrl' => $retryUrl,
                'displayAmount' => number_format($amountInDollars, 2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'returnUrl' => route('payment.return.general'), // Generic return URL
            ]);
        } catch (ApiErrorException $e) {
            Log::error("Web Flow: Stripe Team Slot PI API error for User {$user->id}: " . $e->getMessage());
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Payment Initiation Failed',
                'messageBody' => 'Failed to initiate payment: ' . ($e->getError()?->message ?: 'Stripe API error.')
            ]);
        } catch (\Exception $e) {
            Log::error("Web Flow: Generic error Team Slot PI for User {$user->id}: " . $e->getMessage());
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Error',
                'messageBody' => 'An unexpected error occurred while initiating your payment.'
            ]);
        }
    }

    /**
     * Show payment page for a User to RENEW a specific INDEPENDENT team's activation (Path A).
     * {team} is injected via route model binding from the signed URL.
     * Route Name: 'team.payment.initiate.renewal'
     */
    public function showDirectTeamRenewalPage(Request $request, Team $team): View
    {
        $settings = Settings::instance();
        $amountInDollars = (float) $settings->unlock_price_amount;
        $currency = $settings->unlock_currency;
        $amountInCents = (int) round($amountInDollars * 100);
        $retryUrl = $request->fullUrl();
        $paymentDescription = "Complete your payment to renew premium features for team '{$team->name}' for another " . ($settings->access_duration_days ?? 365) . " days.";
        $user = $team->user; // Owner of the team
        if (!$user) { return view('payments.failed', [
            'displayAmount' => number_format($amountInDollars,2),
            'displayCurrencySymbol' => $settings->unlock_currency_symbol,
            'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
            'currency' => $currency,
            'paymentDescription' => $paymentDescription,
            'type' => 'user',
            'user' => $user,
            'retryUrl' => $retryUrl,
            'amount' => $amountInDollars,'pageTitle' => 'Error', 'messageBody' => 'Team owner not found.']); }

        if ($team->organization_id) {
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,'pageTitle' => 'Renewal Error', 'messageBody' => "Team '{$team->name}' is linked to an organization. Its premium features are managed by the organization's subscription."]);
        }
        // Optional: Logic to allow renewal only if near expiry or expired
        // if ($team->direct_activation_status === 'active' && $team->direct_activation_expires_at && $team->direct_activation_expires_at->gt(now()->addMonths(1))) {
        //     return view('payments.success', ['pageTitle' => 'Activation Current', 'messageBody' => "Team '{$team->name}' activation is current until " . $team->direct_activation_expires_at->toFormattedDayDateString() . "."]);
        // }

        if (strtolower($currency) === 'usd' && $amountInCents < 50) {
            Log::error("Web Payment Error: Slot purchase amount {$amountInCents}c for User ID {$user->id} is below minimum.");
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user', 'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Payment Error',
                'messageBody' => 'The activation price is below the minimum allowed. Please contact support.'
            ]);
        }
        if (empty($currency)) {
            Log::error("Web Payment Error: Currency not set for slot purchase by User ID {$user->id}.");
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Configuration Error',
                'messageBody' => 'Payment currency is not configured. Please contact support.'
            ]);
        }

        try {
            if (!$user->stripe_customer_id) {
                $customer = StripeCustomer::create(['email' => $user->email, 'name' => $user->full_name, 'metadata' => ['app_user_id' => $user->id]]);
                $user->stripe_customer_id = $customer->id;
                $user->saveQuietly();
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents, 'currency' => $currency,
                'customer' => $user->stripe_customer_id,
                'automatic_payment_methods' => ['enabled' => true],
                'description' => "Direct RENEWAL for Team: {$team->name} (ID: {$team->id}) by User {$user->email}",
                'metadata' => [
                    'type' => 'user',
                    'paying_user_id' => $user->id,
                    'team_id' => $team->id,
                    'action' => 'renew_team_direct', // NEW action for webhook
                    'payment_description' => $paymentDescription,
                    'successMsg' => "Your Team Activation Renewal is being processed and will be updated shortly. Check your email for confirmation."
                ],
            ]);
            Log::info("Web Flow: Direct Team Renewal PI {$paymentIntent->id} for Team ID {$team->id}, User ID {$user->id}");

            return view('payments.subscribe_page', [
                'type' => 'user',
                'stripeKey' => config('services.stripe.key'),
                'clientSecret' => $paymentIntent->client_secret,
                'paymentTitle' => "Renew Activation: " . $team->name,
                'paymentDescription' => $paymentDescription,
                'user' => $user,
                'retryUrl' => $retryUrl,
                'displayAmount' => number_format($amountInDollars, 2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'returnUrl' => route('payment.return.general'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error("Web Flow: Stripe Team Slot PI API error for User {$user->id}: " . $e->getMessage());
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Payment Initiation Failed',
                'messageBody' => 'Failed to initiate payment: ' . ($e->getError()?->message ?: 'Stripe API error.')
            ]);
        } catch (\Exception $e) {
            Log::error("Web Flow: Generic error Team Slot PI for User {$user->id}: " . $e->getMessage());
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'paymentDescription' => $paymentDescription,
                'type' => 'user',
                'user' => $user,
                'retryUrl' => $retryUrl,
                'amount' => $amountInDollars,
                'pageTitle' => 'Error',
                'messageBody' => 'An unexpected error occurred while initiating your payment.'
            ]);
        }
    }

    /**
     * Show payment page for an Organization Admin to RENEW their Organization's subscription.
     * {organization} is injected via route model binding from the signed URL.
     * Route: GET /pay-for-organization-renewal/{organization} (Web, Signed, named 'organization.payment.initiate.renewal')
     */
    public function showOrganizationRenewalPage(Request $request, Organization $organization): View
    {
        // $organization is the one whose subscription is being renewed.
        // The 'signed' middleware has validated the URL.

        $settings = Settings::instance();
        $amountInDollars = $organization->getSubscriptionPrice();
//        $amountInDollars = (float) $settings->unlock_price_amount;
        $currency = $settings->unlock_currency;
        $amountInCents = (int) round($amountInDollars * 100);
        $retryUrl = $request->fullUrl();

        // $actingUser for the view can be a generic representation or null if no specific user logged into panel
        // For "Welcome, [User Name]", we might not have a User model if Org Admin logs in with org_code.
        // The view should handle $actingUser being potentially null or an object with a 'name' property.
        $actingUserDisplay = (object)['first_name' => $organization->name]; // Display Organization name

        $paymentDescription = "You are renewing the annual subscription for Organization: {$organization->name} ({$organization->organization_code}).";


        if ($organization->hasActiveSubscription() && $organization->subscription_expires_at->gt(now()->addMonths(1))) { // Example: 1 month buffer
            return view('payments.success', [
                'type' => 'organization',
                'paymentDescription' => $paymentDescription,
                'user' => $actingUserDisplay, // For displaying user info if needed
                'retryUrl' => $retryUrl,
                'displayAmount' => number_format($amountInDollars, 2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'title' => 'Payment Successful!',
                'pageTitle' => 'Subscription Active',
                'messageBody' => "Organization '{$organization->name}' already has an active subscription until " . $organization->subscription_expires_at->toFormattedDayDateString() . ". Renewal can be done closer to the expiry date."
            ]);
        }

        if ($organization->hasActiveSubscription() && $organization->subscription_expires_at->gt(now()->addMonths(11))) {
            return view('payments.pre-activated', [ // Or a different view
                'paymentDescription' => $paymentDescription,
                'type' => 'organization',
                'user' => $actingUserDisplay,
                'retryUrl' => $retryUrl,
                'pageTitle' => 'Subscription Already Active',
                'messageBody' => "Organization '{$organization->name}' already has an active subscription. Renewal can be done closer to the expiry date (" . $organization->subscription_expires_at->toFormattedDayDateString() . ").",
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
            ]);
        }

        // Validate amount and currency before creating PI
        if (strtolower($currency) === 'usd' && $amountInCents < 50) {
            Log::error("Web Flow: PI creation failed for Org ID {$organization->id}: Amount {$amountInCents} cents < minimum.");
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'user' => $actingUserDisplay,
                'retryUrl' => $retryUrl,
                'paymentDescription' => $paymentDescription,
                'type' => 'organization',
                'panel_name' => $organization->name,
                'amount' => $amountInDollars,
                'currency' => $currency,
                'messageBody' => 'Subscription amount is below the minimum allowed.'
            ]);
        }
        if (empty($currency)) {
            Log::error("Web Flow: PI creation failed: Currency not set in settings.");
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'user' => $actingUserDisplay,
                'retryUrl' => $retryUrl,
                'paymentDescription' => $paymentDescription,
                'type' => 'organization',
                'panel_name' => $organization->name,
                'amount' => $amountInDollars,
                'currency' => $currency,
                'messageBody' => 'Payment configuration error (currency).'
            ]);
        }

        try {
            $stripeCustomerIdForPayment = $organization->stripe_customer_id;

            // If the Organization doesn't have its own Stripe Customer ID, create one using its email.
            if (!$stripeCustomerIdForPayment) {
                if (empty($organization->email)) {
                    Log::error("Web Flow Renew Error: Org ID {$organization->id} has no email set to create Stripe Customer.");
                    return view('payments.failed', [
                        'displayAmount' => number_format($amountInDollars,2),
                        'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                        'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                        'currency' => $currency,
                        'user' => $actingUserDisplay,
                        'retryUrl' => $retryUrl,
                        'paymentDescription' => $paymentDescription,
                        'type' => 'organization',
                        'panel_name' => $organization->name,
                        'amount' => $amountInDollars,
                        'pageTitle' => 'Configuration Error',
                        'messageBody' => 'Organization contact email is missing. Cannot proceed with renewal.'
                    ]);
                }
                Log::info("Web Flow Renew: Creating Stripe Customer for Org ID {$organization->id} using email {$organization->email}.");
                $customer = StripeCustomer::create([
                    'email' => $organization->email,
                    'name' => $organization->name, // Use Organization name
                    'metadata' => ['app_organization_id' => $organization->id] // Link Stripe Customer to your Org ID
                ]);
                $stripeCustomerIdForPayment = $customer->id;
                // Save this new Stripe Customer ID back to the Organization record
                $organization->stripe_customer_id = $stripeCustomerIdForPayment;
                $organization->saveQuietly();
            }
            Log::info("Web Flow Renew: Using Stripe Customer ID {$stripeCustomerIdForPayment} for Org ID {$organization->id} renewal.");

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'customer' => $stripeCustomerIdForPayment, // Use the Organization's Stripe Customer ID
                'automatic_payment_methods' => ['enabled' => true],
                'description' => "Renewal Subscription for Organization: {$organization->name} ({$organization->organization_code})",
                'metadata' => [
                    'type' => 'organization',
                    'organization_id' => $organization->id,
                    'organization_code' => $organization->organization_code,
                    // 'paying_user_id' is less relevant here if payment is by "the organization" itself.
                    // Could add creator_user_id if you want to log who *might* be doing it via panel.
                    'action' => 'renew_organization_subscription',
                    'payment_description' => $paymentDescription,
                    'payment_amount' => $amountInDollars,
                    'successMsg' => 'Subscription renewal for '.$organization->name.' is being processed. Your access will be updated shortly.'
                ],
            ]);
            Log::info("Web Flow: Org Renewal PI {$paymentIntent->id} created for Org ID {$organization->id}");


            return view('payments.subscribe_page', [
                'type' => 'organization',
                'stripeKey' => config('services.stripe.key'),
                'clientSecret' => $paymentIntent->client_secret,
                'paymentTitle' => "Renew Subscription: {$organization->name}",
                'paymentDescription' => $paymentDescription,
                'user' => $actingUserDisplay, // Pass an object with a 'name' property for display
                'retryUrl' => $retryUrl,
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency,
                'returnUrl' => route('payment.return.general'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error("Web Flow: Stripe Org Renewal PI API error for Org ID {$organization->id}: " . $e->getMessage());
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'user' => $actingUserDisplay,
                'retryUrl' => $retryUrl,
                'paymentDescription' => $paymentDescription,
                'type' => 'organization', 'panel_name' => $organization->name,'amount' => $amountInDollars,
                'currency' => $currency, 'pageTitle' => 'Renewal Failed',
                'messageBody' => 'Failed to initiate renewal: ' . ($e->getError()?->message ?: 'Stripe API error.')
            ]);
        } catch (\Exception $e) {
            Log::error("Web Flow: Generic error initiating Org Renewal for Org ID {$organization->id}: " . $e->getMessage());
            return view('payments.failed', [
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'user' => $actingUserDisplay,
                'retryUrl' => $retryUrl,
                'paymentDescription' => $paymentDescription,
                'type' => 'organization', 'panel_name' => $organization->name,
                'amount' => $amountInDollars, 'currency' => $currency, 'pageTitle' => 'Renewal Error',
                'messageBody' => 'An unexpected error occurred.'
            ]);
        }
    }

    /**
     * Generic handle return URL from Stripe for both new org and renewal.
     */

    public function handleGenericReturn(Request $request): View
    {
        $paymentIntentId = $request->query('payment_intent');
        $settings = Settings::instance();
        $amountInDollars = (float) $settings->unlock_price_amount;
        $currency = $settings->unlock_currency;
        $amountInCents = (int) round($amountInDollars * 100);
        $retryUrl = null;

        $paymentDescription = "Complete your payment for premium features.";

        $action = $paymentIntent->metadata->action ?? 'payment';
        $messageBody = isset($paymentIntent->metadata->successMsg) ? $paymentIntent->metadata->successMsg : 'Your account or service status will be updated shortly via email.';
        $pageTitle = 'Payment Successful!';

        if (!$paymentIntentId) {
            return view('payments.failed', [
                'paymentDescription' => $paymentDescription,
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'retryUrl' => $retryUrl,
                'currency' => $currency, 'title'=>'Error', 'messageBody'=>'Payment details missing.']);
        }
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            Log::info("Generic Payment Return: PI {$paymentIntent->id}, Status: {$paymentIntent->status}, Action: " . ($paymentIntent->metadata->action ?? 'N/A'));

            // For display purposes, try to get context
            $actingUser = null;
            $type = $paymentIntent->metadata->type;
            $pageTitle = "Payment Status";
            if ($action === 'purchase_team_activation_slot' && isset($paymentIntent->metadata->paying_user_id)) {
                $retryUrl = URL::temporarySignedRoute(
                    'team_activation_slot.payment.initiate.web', now()->addMinutes(30),
                    ['user' => $paymentIntent->metadata->paying_user_id]
                );
            } elseif ($action === 'renew_team_direct') {
                $retryUrl = URL::temporarySignedRoute(
                    'team.payment.initiate.renewal', // Name of the new web route
                    now()->addMinutes(30),
                    ['team' => $paymentIntent->metadata->team_id] // Pass team ID
                );
            } elseif ($action === 'renew_organization_subscription' && isset($paymentIntent->metadata->organization_id)) {
                $retryUrl = URL::temporarySignedRoute(
                    'organization.payment.initiate.renewal', now()->addMinutes(30),
                    ['organization' => $paymentIntent->metadata->organization_id]
                );
            }

            if (isset($paymentIntent->metadata->payment_amount)){
                $amountInDollars = $paymentIntent->metadata->payment_amount;
                $amountInCents = (int) round($amountInDollars * 100);
            }
            if (isset($paymentIntent->metadata->paying_user_id)) {
                $actingUser = User::find($paymentIntent->metadata->paying_user_id);
                $paymentDescription = $paymentIntent->metadata->payment_description;
            } elseif (isset($paymentIntent->metadata->organization_id)) {
                $org = Organization::find($paymentIntent->metadata->organization_id);
                $actingUser = $org ? (object)['first_name' => $org->name] : null; // Use org name for display
                $paymentDescription = $paymentIntent->metadata->payment_description;
            }

            if ($paymentIntent->status === 'succeeded') {
                $action = $paymentIntent->metadata->action ?? 'payment';
                $messageBody = isset($paymentIntent->metadata->successMsg) ? $paymentIntent->metadata->successMsg : 'Your account or service status will be updated shortly via email.';
                $pageTitle = 'Payment Successful!';

//                if ($action === 'purchase_team_activation_slot') {
//                    $messageBody .= 'Your Team Activation Slot is being processed and will be available shortly. Check your email for confirmation.';
//                } elseif ($action === 'renew_organization_subscription') {
//                    $orgName = $paymentIntent->metadata->organization_name_from_pi ?? ($paymentIntent->metadata->organization_code ?? 'Your organization');
//                    $messageBody .= "Subscription renewal for '{$orgName}' is being processed. Your access will be updated shortly.";
//                } else {
//                    $messageBody .= 'Your account or service status will be updated shortly via email.';
//                }
                return view('payments.success', [
                    'paymentDescription' => $paymentDescription,
                    'type' => $type,
                    'displayAmount' => number_format($amountInDollars,2),
                    'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                    'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                    'retryUrl' => $retryUrl,
                    'currency' => $currency, 'title' => $pageTitle, 'messageBody' => $messageBody, 'user' => $actingUser]);
            } elseif ($paymentIntent->status === 'processing') {
                return view('payments.processing', [
                    'paymentDescription' => $paymentDescription,
                    'retryUrl' => $retryUrl,
                    'displayAmount' => number_format($amountInDollars,2),
                    'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                    'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                    'currency' => $currency, 'title'=>'Payment Processing', 'messageBody'=>'Your payment is processing. We will notify you once confirmed.', 'actingUser' => $actingUser]);
            } else {
                $failureReason = $paymentIntent->last_payment_error->message ?? 'Your payment was not successful. Please try again or use a different payment method.';
                return view('payments.failed', [
                    'paymentDescription' => $paymentDescription,
                    'retryUrl' => $retryUrl,
                    'displayAmount' => number_format($amountInDollars,2),
                    'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                    'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                    'currency' => $currency, 'title'=>'Payment Issue', 'messageBody'=> $failureReason, 'actingUser' => $actingUser]);
            }
        } catch (ApiErrorException $e) {
            Log::error("Generic Payment Return: Stripe API Error for PI {$paymentIntentId}: " . $e->getMessage());
            return view('payments.failed', [
                'paymentDescription' => $paymentDescription,
                'retryUrl' => $retryUrl,
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency, 'title'=>'Verification Error', 'messageBody'=>'Could not verify your payment status. Check email or contact support.']);
        } catch (\Exception $e) {
            Log::error("Generic Payment Return: General Error for PI {$paymentIntentId}: " . $e->getMessage());
            return view('payments.failed', [
                'paymentDescription' => $paymentDescription,
                'retryUrl' => $retryUrl,
                'displayAmount' => number_format($amountInDollars,2),
                'displayCurrencySymbol' => $settings->unlock_currency_symbol,
                'displayCurrencySymbolPosition' => $settings->unlock_currency_symbol_position,
                'currency' => $currency, 'title'=>'Error', 'messageBody'=>'An unexpected error occurred. Check email or contact support.']);
        }
    }
}