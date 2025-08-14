<?php
namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Team;
use App\Models\Settings; // For renewal price
use App\Models\User;
use App\Mail\OrganizationSubscriptionRenewedViaPromoMail;
use App\Mail\OrganizationPasswordResetOtpMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rules\Password;
use Stripe\PaymentIntent; // For renewal
use Stripe\Stripe;      // For renewal
use Stripe\Exception\ApiErrorException; // For renewal

class OrganizationPanelController extends Controller
{
    use ApiResponseTrait;
    protected $guard = 'api_org_admin'; // Define guard
    protected const OTP_EXPIRY_MINUTES = 10; // OTP expiry time for orgs

    public function __construct() {
        Stripe::setApiKey(config('services.stripe.secret')); // For renewal intent
        Stripe::setApiVersion('2024-04-10');
    }


    protected function formatTokenResponse($token, Organization $organization) {
        return [
            'access_token' => $token, 'token_type' => 'bearer',
            'expires_in' => auth($this->guard)->factory()->getTTL() * 60,
            'user_type' => 'organization_admin',
            'organization' => $organization->only(['id', 'name', 'organization_code', 'email', 'subscription_status', 'subscription_expires_at', 'creator_user_id'])
        ];
    }

    /**
     * Authenticates an Organization Admin using either their email OR organization_code.
     * Route: POST /organization-panel/auth/login
     */
    public function login(Request $request)
    {
        // The 'username' field from the client can contain either an email or an org code.
        $validator = Validator::make($request->all(), [
            'username' => 'required|string', // A single field for either email or code
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $username = $request->input('username');
        $password = $request->input('password');

        $token = null;

        // --- Attempt 1: Log in using the input as an EMAIL ---
        $credentialsAsEmail = ['email' => $username, 'password' => $password];
        $token = auth($this->guard)->attempt($credentialsAsEmail);

        // --- Attempt 2: If email login fails, log in using the input as an ORGANIZATION_CODE ---
        if (!$token) {
            $credentialsAsCode = ['organization_code' => strtoupper($username), 'password' => $password];
            $token = auth($this->guard)->attempt($credentialsAsCode);
        }

        // --- Check the result ---
        if (!$token) {
            // Both attempts failed. Return a generic error to prevent user enumeration.
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        /** @var Organization $organization */
        $organization = auth($this->guard)->user();
        return $this->successResponse($this->formatTokenResponse($token, $organization), 'Organization login successful.');
    }

    public function loginOld(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email', // Changed from organization_code
            'password' => 'required|string',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        // Attempt login using 'email'
        $credentials = $request->only('email', 'password');

        if (!$token = auth($this->guard)->attempt($credentials)) {
            $org = Organization::where('email', $credentials['email'])->first();
            if (!$org) return $this->errorResponse('Organization email not found.', Response::HTTP_UNAUTHORIZED);
            return $this->errorResponse('Incorrect password for organization.', Response::HTTP_UNAUTHORIZED);
        }
        /** @var Organization $organization */
        $organization = auth($this->guard)->user();
        return $this->successResponse($this->formatTokenResponse($token, $organization), 'Organization login successful.');
    }

    public function logout(Request $request) {
        try { auth($this->guard)->logout(); return $this->successResponse(null, 'Successfully logged out.'); }
        catch (\Exception $e) { return $this->errorResponse('Could not log out.', Response::HTTP_INTERNAL_SERVER_ERROR); }
    }

    public function profile(Request $request) { // Org Profile
        /** @var Organization $organization */
        $organization = $request->user(); // Authenticated Organization
        return $this->successResponse($organization->only(['id','name','email','organization_code', 'annual_team_allocation', 'teams_created_this_period','subscription_status','subscription_expires_at','creator_user_id']));
    }

    public function subscriptionActivationDetails(Request $request) { // Org Profile
        /** @var Organization $organization */

        $organization = $request->user(); // Authenticated Organization
        if (!$organization->hasActiveSubscription()) {
            return $this->forbiddenResponse("Your subscription is currently inactive. Please complete the payment to activate your subscription and receive team credits.");
        }
        return $this->successResponse($organization->only(['id','name','email','organization_code', 'annual_team_allocation', 'teams_created_this_period','subscription_status','subscription_expires_at','creator_user_id']));
    }

    public function changePassword(Request $request) {
        /** @var Organization $organization */
        $organization = $request->user();
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', function ($attribute, $value, $fail) use ($organization) {
                if (!Hash::check($value, $organization->password)) $fail('Current password incorrect.');
            }],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);
        $organization->password = $request->password; // Model cast will hash
        $organization->save();
        return $this->successResponse(null, 'Organization password changed successfully.');
    }

    public function listTeams(Request $request) {
        /** @var Organization $organization */
        $organization = $request->user();
        $teams = $organization->teams()->with('user:id,first_name,last_name,email')->with('organization:id,name,email,organization_code') // Show team owner
        ->orderBy('id', 'desc')->paginate($request->input('per_page', 15));
        return $this->successResponse($teams, 'Teams retrieved successfully.');
    }

    /**
     * Authenticated Organization Admin redeems a promo code to renew THEIR Organization's subscription.
     * Route: POST /organization-panel/subscription/redeem-promo
     */
    public function redeemPromoForRenewalOld(Request $request)
    {
        /** @var \App\Models\Organization $organization */
        $organization = $request->user(); // Get the authenticated Organization model instance

        if (!$organization) {
            // Should be caught by auth:api_org_admin middleware
            return $this->unauthorizedResponse('Organization not authenticated.');
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $promoCodeString = strtoupper($request->input('code'));

        $promoCode = PromoCode::where('code', $promoCodeString)->first();
        if (!$promoCode) return $this->notFoundResponse('Invalid promo code provided.');

        // --- Promo Code Validation ---
        if (!$promoCode->is_active) return $this->errorResponse('This promo code is not active.', Response::HTTP_BAD_REQUEST);
        if ($promoCode->expires_at && $promoCode->expires_at->isPast()) return $this->errorResponse('This promo code has expired.', Response::HTTP_BAD_REQUEST);
        if ($promoCode->hasReachedMaxUses()) return $this->errorResponse('This promo code has reached its global usage limit.', Response::HTTP_BAD_REQUEST);

        // --- Organization's Usage Limit for this Specific Promo Code ---
        // An organization should typically only be able to use a specific renewal promo once,
        // or based on promo code's rules (max_uses_per_user for promo codes could be interpreted as max_uses_per_entity).
        // Let's assume an organization can use a given promo code only once for renewal.
        $orgRedemptionCount = PromoCodeRedemption::where('organization_id', $organization->id)
            ->where('promo_code_id', $promoCode->id)
            ->count();

        // If max_uses_per_user on PromoCode means "max uses per redeeming entity"
        // Here, the "entity" is the Organization itself.
        if ($orgRedemptionCount >= $promoCode->max_uses_per_user) { // Typically 1
            return $this->errorResponse(
                'This promo code has already been used by your organization the maximum number of times.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Optional: Check if subscription is already very far in the future
        // if ($organization->hasActiveSubscription() && $organization->subscription_expires_at->gt(now()->addMonths(11))) {
        // return $this->errorResponse('Subscription renewal can only be done closer to the expiry date.', Response::HTTP_CONFLICT);
        // }

        $actualExpiryDate = null;

        try {
            DB::transaction(function () use ($organization, $promoCode, &$actualExpiryDate, $request) {
                $settings = Settings::instance();
                $durationDays = $settings->access_duration_days > 0 ? $settings->access_duration_days : 365;

                // Calculate new expiry: add duration to current expiry if active & future, else from now
                $newExpiry = $organization->subscription_expires_at && $organization->subscription_expires_at->isFuture()
                    ? $organization->subscription_expires_at->addDays($durationDays)
                    : Carbon::now()->addDays($durationDays);

                $organization->subscription_status = 'active';
                $organization->subscription_expires_at = $newExpiry;
                // No change to org code or password during renewal
                $organization->save();

                $actualExpiryDate = $newExpiry;

                // Record the redemption, linking to the organization and the user who performed the action (org admin)
                // The `creator_user_id` of the organization is likely the one performing this action.
                $actingUserId = $organization->creator_user_id; // Assume creator is the one logged into panel

                PromoCodeRedemption::create([
                    'user_id' => $actingUserId, // User who (as org admin) redeemed it
                    'promo_code_id' => $promoCode->id,
                    'organization_id' => $organization->id,
                    'redeemed_at' => now()
                ]);
                $promoCode->increment('use_count');

                Log::info("Org ID {$organization->id} subscription RENEWED via promo '{$promoCode->code}'. New Expiry: {$newExpiry->toIso8601String()}");
            });

            $durationString = $this->getHumanReadableDuration(Settings::instance()->access_duration_days);
            $message = "Promo code redeemed! Subscription for organization '{$organization->name}' has been renewed {$durationString}.";

            return $this->successResponse(
                [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'organization_code' => $organization->organization_code,
                    'subscription_status' => $organization->subscription_status,
                    'subscription_expires_at' => $actualExpiryDate?->toISOString()
                ],
                $message
            );

        } catch (\Exception $e) {
            Log::error("Organization promo renewal failed: Org ID {$organization->id}, Code: {$promoCode->code}, Error: " . $e->getMessage(), ['exception' => $e]);
            return $this->errorResponse('Failed to renew subscription with promo code.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Authenticated Organization Admin redeems a promo code to RENEW their Organization's subscription.
     * Route: POST /organization-panel/subscription/redeem-promo
     */
    public function redeemPromoForRenewal(Request $request)
    {
        /** @var \App\Models\Organization $organization */
        $organization = $request->user(); // Get the authenticated Organization model instance

        if (!$organization) {
            return $this->unauthorizedResponse('Organization not authenticated.');
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $promoCodeString = strtoupper($request->input('code'));

        $promoCode = PromoCode::where('code', $promoCodeString)->first();
        if (!$promoCode) return $this->notFoundResponse('Invalid promo code provided.');

        // --- Promo Code Validation ---
        if (!$promoCode->is_active) return $this->errorResponse('This promo code is not active.', Response::HTTP_BAD_REQUEST);
        if ($promoCode->expires_at && $promoCode->expires_at->isPast()) return $this->errorResponse('This promo code has expired.', Response::HTTP_BAD_REQUEST);
        if ($promoCode->hasReachedMaxUses()) return $this->errorResponse('This promo code has reached its global usage limit.', Response::HTTP_BAD_REQUEST);

        // --- Organization's Usage Limit for this Specific Promo Code ---
        // An organization should typically only be able to use a specific renewal promo once,
        // or based on promo code's max_uses_per_user (interpreted as max_uses_per_org here).
        $orgRedemptionCountForThisPromo = PromoCodeRedemption::where('organization_id', $organization->id)
            ->where('promo_code_id', $promoCode->id)
            ->count();

        if ($orgRedemptionCountForThisPromo >= $promoCode->max_uses_per_user) { // Typically 1
            return $this->errorResponse(
                'This promo code has already been used by your organization the maximum number of times allowed for renewal.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Optional: Check if subscription is already very far in the future
        // if ($organization->hasActiveSubscription() && $organization->subscription_expires_at->gt(now()->addMonths(11))) {
        //     return $this->errorResponse('Subscription renewal with a promo can typically be done closer to the expiry date.', Response::HTTP_CONFLICT);
        // }

        $actualExpiryDate = null;

        DB::beginTransaction();
        try {
            $settings = Settings::instance();
            $durationDays = $settings->access_duration_days > 0 ? $settings->access_duration_days : 365;

            // Calculate new expiry: add duration to current expiry if active & future, else from now
            $newExpiryDate = $organization->subscription_expires_at && $organization->subscription_expires_at->isFuture()
                ? $organization->subscription_expires_at->addDays($durationDays)
                : Carbon::now()->addDays($durationDays);

            $organization->subscription_status = 'active'; // Ensure active
            $organization->subscription_expires_at = $newExpiryDate;
            $organization->teams_created_this_period = 0; // Reset team allocation on renewal
            // No change to org code or password during renewal via promo
            $organization->save();

            $actualExpiryDate = $newExpiryDate;

            // Record the redemption
            PromoCodeRedemption::create([
                'user_id' => null,
                'promo_code_id' => $promoCode->id,
                'organization_id' => $organization->id, // Link to the renewed org
                'redeemable_id' => $organization->id,     // Polymorphic link
                'redeemable_type' => Organization::class, // Polymorphic link
                'redeemed_at' => now()
            ]);
            $promoCode->increment('use_count'); // Increment global use count

            DB::commit();
            Log::info("Org ID {$organization->id} subscription RENEWED via promo '{$promoCode->code}'. New Expiry: {$newExpiryDate->toIso8601String()}");

            // --- Send Organization Renewal Success Email ---
            // The recipient is the organization's contact email.
            // The "user" context for the email can be the organization's name or its creator.
            if ($organization->email) {
                $orgAdminContextUser = $organization->creator ?? new User(['first_name' => $organization->name]);
                try {
                    // Note: OrganizationSubscriptionRenewedMail expects a Payment object.
                    // We need a different mailable or adapt it for promo renewals.
                    // For now, logging TODO.
                     Mail::to($organization->email)->send(new OrganizationSubscriptionRenewedViaPromoMail($organization, $orgAdminContextUser, $promoCode));
                    Log::info("TODO: Send OrganizationSubscriptionRenewedViaPromoMail to {$organization->email} for Org ID {$organization->id}");
                } catch (\Exception $e) {
                    Log::error("Mail Error (OrgSubRenewedViaPromo to Org Email): {$e->getMessage()}");
                }
            }


            $durationString = $this->getHumanReadableDuration($durationDays);
            $message = "Promo code redeemed! Subscription for organization '{$organization->name}' has been renewed {$durationString}.";

            return $this->successResponse(
                [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'organization_code' => $organization->organization_code,
                    'subscription_status' => $organization->subscription_status,
                    'subscription_expires_at' => $actualExpiryDate?->toISOString(),
                    'annual_team_allocation' => $organization->annual_team_allocation,
                    'teams_created_this_period' => $organization->teams_created_this_period,
                ],
                $message
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Organization promo renewal failed: Org ID {$organization->id}, Code: {$promoCodeString}, Error: " . $e->getMessage(), ['exception' => $e]);
            return $this->errorResponse('Failed to renew subscription with promo code.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function showTeam(Request $request, Team $team) {
        /** @var Organization $organization */
        $organization = $request->user();
        if ($team->organization_id !== $organization->id) return $this->forbiddenResponse('This team does not belong to your organization.');
        $team->load(['user:id,first_name,last_name,email', 'players' => fn($q) => $q->select('id','team_id','first_name','last_name','jersey_number')]);
        return $this->successResponse($team);
    }

    public function deleteTeam(Request $request, Team $team) {
        /** @var Organization $organization */
        $organization = $request->user();
        if ($team->organization_id !== $organization->id) return $this->forbiddenResponse('This team does not belong to your organization.');
        $team->delete();
        return $this->successResponse(null, 'Team deleted successfully from organization.', Response::HTTP_OK, false);
    }

    public function createSubscriptionRenewalIntent(Request $request) {
        /** @var Organization $organization */
        $organization = $request->user();

        // Optionally check if subscription is already very far in future, or near expiry
        // if ($organization->hasActiveSubscription() && $organization->subscription_expires_at->gt(now()->addMonths(11))) {
        //    return $this->errorResponse('Subscription renewal can only be done closer to expiry.', Response::HTTP_CONFLICT);
        // }

        $settings = Settings::instance(); /* ... get amount/currency ... */
        $amountInDollars = (float)$settings->unlock_price_amount; $currency = $settings->unlock_currency;
        $amountInCents = (int)round($amountInDollars * 100);

        try {
            if (!$organization->stripe_customer_id) { // Org should have a Stripe customer ID if previously paid
                $customer = \Stripe\Customer::create(['email' => $organization->email ?? $organization->creator?->email, 'name' => $organization->name, 'metadata' => ['organization_id' => $organization->id]]);
                $organization->stripe_customer_id = $customer->id;
                $organization->saveQuietly();
            }
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents, 'currency' => $currency,
                'customer' => $organization->stripe_customer_id,
                'automatic_payment_methods' => ['enabled' => true],
                'description' => "Renewal Subscription for Organization: {$organization->name} ({$organization->organization_code})",
                'metadata' => [ 'organization_id' => $organization->id, 'organization_code' => $organization->organization_code, 'renewal' => 'true' ],
            ]);
            return $this->successResponse([ 'clientSecret' => $paymentIntent->client_secret, /* ... other details ... */ ], 'Renewal Payment Intent created.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the authenticated Organization's subscription activation history
     * (both paid renewals and promo code activations for the organization).
     * Route: GET /organization-panel/subscription/activation-history
     */
    public function subscriptionActivationHistory(Request $request)
    {
        /** @var \App\Models\Organization $organization */
        $organization = $request->user(); // Authenticated Organization

        // Fetch Payments made for this Organization's subscription
        $paidActivations = $organization->payments() // Assumes payments relationship on Org model
        ->with('user:id,first_name,last_name,email') // User who made the payment
        ->where('status', 'succeeded')
            ->select('id as record_id', 'user_id', 'amount', 'currency', 'paid_at as event_at', DB::raw("'Payment' as type"))
            ->get();

        // Fetch Promo Code Redemptions for this Organization
        $promoActivations = $organization->promoCodeRedemptions() // Assumes promoCodeRedemptions relationship
        ->with(['user:id,first_name,last_name,email', 'promoCode:id,code,description']) // User who redeemed
        ->select('id as record_id', 'user_id', 'promo_code_id', 'redeemed_at as event_at', DB::raw("'Promo Code' as type"))
            ->get();

        $history = $paidActivations->map(function ($payment) {
            return [
                'record_id' => $payment->record_id,
                'type' => "Paid",
                'date' => Carbon::parse($payment->event_at)->toIso8601String(),
                'amount_display' => $payment->amount, // Accessor handles dollar conversion
                'currency' => $payment->currency,
                'status' => 'Succeeded', // Since we filtered for succeeded payments
                'activated_by_user_id' => $payment->user_id,
                'activated_by_user_name' => $payment->user?->full_name,
                'description' => "Subscription renewal payment.",
            ];
        })->concat(
            $promoActivations->map(function ($redemption) {
                return [
                    'record_id' => $redemption->record_id,
                    'type' => "Promo",
                    'date' => Carbon::parse($redemption->event_at)->toIso8601String(),
                    'promo_code' => $redemption->promoCode?->code,
                    'activated_by_user_id' => $redemption->user_id,
                    'activated_by_user_name' => $redemption->user?->full_name,
                    'description' => "Subscription activated/renewed via promo: " . ($redemption->promoCode?->description ?? 'N/A'),
                ];
            })
        )->sortByDesc('date')->values();

        $perPage = $request->input('per_page', 15);
        $currentPage = $request->input('page', 1);
        $currentPageItems = $history->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginatedHistory = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems, $history->count(), $perPage, $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->successResponse($paginatedHistory, 'Organization activation history retrieved.');
    }

    /**
     * Generates a secure, temporary signed URL for the Organization Admin
     * to renew their Organization's subscription via a web page.
     * Route: POST /organization-panel/subscription/generate-renewal-link (Requires Org Admin Auth)
     */
    public function generateWebRenewalLink(Request $request)
    {
        /** @var \App\Models\Organization $organization */
        $organization = $request->user(); // Authenticated Organization

        // Optional: Check if renewal is appropriate (e.g., not too early)
        if ($organization->hasActiveSubscription() && $organization->subscription_expires_at->gt(now()->addMonths(1))) { // Example: 1 month buffer
            return $this->errorResponse(
                'Subscription renewal can typically be done closer to the expiry date.',
                Response::HTTP_CONFLICT,
                ['expires_at' => $organization->subscription_expires_at->toFormattedDayDateString()]
            );
        }

        try {
            $signedUrl = URL::temporarySignedRoute(
                'organization.payment.initiate.renewal', // <-- Use the new route name for renewal
                now()->addMinutes(30),
                ['organization' => $organization->id] // Pass the organization ID
            );
            return $this->successResponse(['payment_url' => $signedUrl], 'Secure renewal payment link generated.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate renewal link for Org ID {$organization->id}: " . $e->getMessage());
            return $this->errorResponse('Could not generate renewal link.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // --- NEW: Organization Password Reset Methods (Public for Org Panel context) ---

    /**
     * Send Password Reset OTP to Organization's Email.
     * Route: POST /organization-panel/auth/forgot-password (Public for Org Panel)
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:organizations,email', // Check against org emails
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $organization = Organization::where('email', $request->email)->first();
        if (!$organization) {
            // Should be caught by 'exists' rule, but good to double check
            return $this->notFoundResponse('Organization with this email not found.');
        }
        if (empty($organization->email)) { // Extra check if email was somehow nullable and empty
            return $this->errorResponse('This organization does not have a registered email for password reset.', Response::HTTP_BAD_REQUEST);
        }


        $otp = Str::padLeft((string) random_int(0, 999999), 6, '0');
        $expiresAt = Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES)->toDateTimeString();
        $now = Carbon::now()->toDateTimeString();

        try {
            // Using DB::table for password_reset_otps table.
            // The primary key 'email' will now store the Organization's email.
            DB::table('password_reset_otps')->updateOrInsert(
                ['email' => $organization->email],
                ['otp' => $otp, 'expires_at' => $expiresAt, 'created_at' => $now]
            );
            Log::info("OTP stored/updated for Organization email: {$organization->email}");

        } catch (\Exception $e) {
            Log::error('Failed to store password reset OTP for Organization: ' . $e->getMessage(), [
                'organization_email' => $organization->email, 'exception' => $e
            ]);
            return $this->errorResponse('Could not process your password reset request.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            Mail::to($organization->email)->send(new OrganizationPasswordResetOtpMail($otp, $organization));
            return $this->successResponse(null, 'Password reset OTP has been sent to the organization\'s contact email.', Response::HTTP_OK, false);
        } catch (\Exception $e) {
            Log::error('Failed to send org password reset OTP email: ' . $e->getMessage(), ['organization_email' => $organization->email]);
            return $this->errorResponse('Could not send OTP email. Please try again later.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reset Organization's Password using OTP.
     * Route: POST /organization-panel/auth/reset-password (Public for Org Panel)
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:organizations,email', // Org's email
            'otp' => 'required|string|digits:6',
            'password' => ['required', 'confirmed', Password::defaults()], // New password for the org
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $otpRecord = DB::table('password_reset_otps')
            ->where('email', $request->email) // Org's email
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord) {
            return $this->errorResponse('Invalid or incorrect OTP.', Response::HTTP_BAD_REQUEST);
        }

        if (Carbon::parse($otpRecord->expires_at)->isPast()) {
            DB::table('password_reset_otps')->where('email', $request->email)->delete();
            return $this->errorResponse('OTP has expired. Please request a new one.', Response::HTTP_BAD_REQUEST);
        }

        $organization = Organization::where('email', $request->email)->first();
        if (!$organization) { // Should be caught by 'exists' rule
            return $this->notFoundResponse('Organization not found for password reset.');
        }

        try {
            $organization->password = $request->password; // Model's $casts will hash it
            $organization->save();

            DB::table('password_reset_otps')->where('email', $request->email)->delete();

            // Optionally: Invalidate any active JWTs for this organization if possible/needed
            // This is complex with JWTs as they are stateless.
            // Forcing a re-login by just changing the password is often sufficient.

            return $this->successResponse(null, 'Organization panel password has been reset successfully.', Response::HTTP_OK, false);
        } catch (\Exception $e) {
            Log::error('Failed to reset organization password for: ' . $request->email . '. Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->errorResponse('Could not reset password at this time.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}