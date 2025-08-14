<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Auth Controllers ---
use App\Http\Controllers\Api\V1\Auth\UniversalLoginController;
use App\Http\Controllers\Api\V1\Auth\UserAuthController;    // For user-specific auth actions (profile, pwd change)
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;   // For admin-specific auth actions

// --- User-Facing Resource & Action Controllers ---
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\PlayerController;
use App\Http\Controllers\Api\V1\PlayerPreferenceController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\PromoCodeController as UserActionPromoCodeController; // Renamed for clarity
use App\Http\Controllers\Api\V1\StripeController;
use App\Http\Controllers\Api\V1\OrganizationController; // For user to view orgs
use App\Http\Controllers\Api\V1\PositionController;       // For user to view positions
//use App\Http\Controllers\Api\V1\AppConfigController;

// --- Admin Resource Management Controllers ---
use App\Http\Controllers\Api\V1\Admin\AdminOrganizationController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;       // Manages User accounts
use App\Http\Controllers\Api\V1\Admin\AdminPromoCodeController;  // Manages Promo Codes
use App\Http\Controllers\Api\V1\Admin\AdminPaymentController;    // Views Payments
use App\Http\Controllers\Api\V1\Admin\AdminSettingsController;   // Manages App Settings
use App\Http\Controllers\Api\V1\Admin\AdminUtilityController;    // For migrations/seeds

// --- Organization Panel Controller ---
use App\Http\Controllers\Api\V1\Organization\OrganizationPanelController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\Admin\AdminTeamController;
/*
|--------------------------------------------------------------------------
| API Routes V1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- PUBLIC ROUTESs ---
    Route::post('contact-us', [ContactController::class, 'submitContactForm']);
    Route::post('auth/login', [UniversalLoginController::class, 'login']); // Universal login

    Route::prefix('user/auth')->controller(UserAuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
        Route::post('forgot-password', 'forgotPassword');
        Route::post('reset-password', 'resetPassword');
    });

    Route::post('stripe/webhook', [StripeController::class, 'handleWebhook'])->name('stripe.webhook.api');
//    Route::get('app-config', [AppConfigController::class, 'getPublicConfig']);


    // --- USER AUTHENTICATED ROUTES (auth:api_user) ---
    Route::middleware('auth:api_user')->group(function () {
        Route::prefix('user/auth')->controller(UserAuthController::class)->group(function () {
            Route::post('logout', 'logout');
            Route::post('refresh', 'refresh');
            Route::get('profile', 'profile');
            Route::put('profile', 'updateProfile');
            Route::post('change-password', 'changePassword');
            Route::post('validate-organization-access-code', 'validateOrganizationAccessCode');
        });

        // Team Management (User creates team under an Org Code they provide)
        Route::apiResource('teams', TeamController::class);
        Route::get('teams/{team}/players', [TeamController::class, 'listPlayers']);
        Route::get('teams/{team}/check-editability', [TeamController::class, 'checkEditability']);
        Route::get('teams/{team}/generate-renewal-link', [StripeController::class, 'generateDirectTeamRenewalLink']);

        // Player Management
        Route::post('teams/{team}/players', [PlayerController::class, 'store']);
        Route::get('players/{player}', [PlayerController::class, 'show']);
        Route::put('players/{player}', [PlayerController::class, 'update']);
        Route::delete('players/{player}', [PlayerController::class, 'destroy']);

        // Player Preferences
        Route::post('players/{player}/preferences', [PlayerPreferenceController::class, 'store']);
        Route::get('players/{player}/preferences', [PlayerPreferenceController::class, 'show']);
        Route::get('teams/{team}/bulk-player-preferences', [PlayerPreferenceController::class, 'bulkShowByTeam']);
        Route::put('teams/{team}/bulk-player-preferences', [PlayerPreferenceController::class, 'bulkUpdateByTeam']);

        // Game Management
        Route::get('teams/{team}/games', [GameController::class, 'index']);
        Route::post('teams/{team}/games', [GameController::class, 'store']);
        Route::apiResource('games', GameController::class)->except(['index', 'store']); // show, update, destroy for /games/{game}

        // Lineup & PDF
        Route::get('games/{game}/lineup', [GameController::class, 'getLineup']);
        Route::put('games/{game}/lineup', [GameController::class, 'updateLineup']);
        Route::post('games/{game}/autocomplete-lineup', [GameController::class, 'autocompleteLineup']);
        Route::post('games/{game}/placeholder-lineup', [GameController::class, 'placeholderLineup']);
        Route::get('games/{game}/pdf-data', [GameController::class, 'getLineupPdfData']);

        // Path A: Direct Team Activation (User pays/promos for a "team activation slot" THEN creates team using it)
        Route::post('team-activation-slots/create-payment-intent', [StripeController::class, 'createTeamActivationSlotPaymentIntent']);
        Route::get('team-activation-slots/generate-payment-link', [StripeController::class, 'generateTeamActivationSlotWebLink']);
        Route::get('available-team-slots', [UserAuthController::class, 'getAvailableTeamSlotsCount']);

        // User redeems Promo Code (to create/activate a new Organization)
        Route::post('promo-codes/redeem', [UserActionPromoCodeController::class, 'redeem']);
        Route::get('promo-codes/redemption-history', [UserActionPromoCodeController::class, 'redemptionHistory']);

        // User Payment History (payments they made for orgs)
        Route::get('payments/history', [StripeController::class, 'userPaymentHistory']);

        // Activation History
        Route::get('activation-history', [UserAuthController::class, 'activationHistory']);

        // Supporting Lists for User UI
        Route::get('organizations', [OrganizationController::class, 'index']);
        Route::get('organizations/by-code/{organization_code}', [OrganizationController::class, 'showByCode']);
        Route::get('positions', [PositionController::class, 'index']);
        Route::get('payment-details', [StripeController::class, 'getPaymentDetails']); // Global payment price details
    });

    Route::prefix('admin/auth')->controller(AdminAuthController::class)->group(function () {
        Route::post('login', 'login');
    });

    // --- ADMIN AUTHENTICATED ROUTES (auth:api_admin) ---
    Route::prefix('admin')->middleware('auth:api_admin')->group(function () {
        Route::prefix('auth')->controller(AdminAuthController::class)->group(function() {
            Route::post('logout', 'logout');
            Route::post('refresh', 'refresh');
            Route::get('profile', 'profile');
            Route::put('profile', 'updateProfile');
            Route::post('change-password', 'changePassword');
        });

        Route::apiResource('teams', AdminTeamController::class);

        Route::apiResource('organizations', AdminOrganizationController::class); // Admin full CRUD for Orgs
        Route::apiResource('positions', PositionController::class);     // Admin full CRUD for Positions
        Route::apiResource('users', AdminUserController::class);             // Admin manages User accounts
        Route::apiResource('promo-codes', AdminPromoCodeController::class);  // Admin manages Promo Codes

        Route::get('payments', [AdminPaymentController::class, 'index']);
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show']);

        Route::get('settings', [AdminSettingsController::class, 'show']);
        Route::put('settings', [AdminSettingsController::class, 'update']);

        Route::prefix('utils')->controller(AdminUtilityController::class)->group(function () {
            Route::post('migrate-and-seed', 'migrateAndSeed');
            Route::post('migrate-fresh-and-seed', 'migrateFreshAndSeed');
        });
    });


    // --- ORGANIZATION PANEL ROUTES ---
    // Public login for Organization Panel (uses Organization code as username)
    Route::prefix('organization-panel/auth')->controller(OrganizationPanelController::class)->group(function () {
        Route::post('login', 'login'); // Existing public login

        // NEW Public routes for Organization Password Reset
        Route::post('forgot-password', 'forgotPassword');
        Route::post('reset-password', 'resetPassword');
    });

    // Protected Organization Panel Routes (Requires Organization JWT: auth:api_org_admin)
    Route::prefix('organization-panel')->middleware('auth:api_org_admin')->group(function () {
        Route::post('auth/logout', [OrganizationPanelController::class, 'logout']);
        Route::get('auth/profile', [OrganizationPanelController::class, 'profile']); // Organization's own details
        Route::post('auth/change-password', [OrganizationPanelController::class, 'changePassword']);

        Route::get('teams', [OrganizationPanelController::class, 'listTeams']);
        Route::get('teams/{team}', [OrganizationPanelController::class, 'showTeam']);
        Route::delete('teams/{team}', [OrganizationPanelController::class, 'deleteTeam']);

        // Organization renews its own subscription
        Route::post('subscription/create-renewal-intent', [OrganizationPanelController::class, 'createSubscriptionRenewalIntent']);
        Route::get('subscription/generate-renewal-link', [OrganizationPanelController::class, 'generateWebRenewalLink']);

        Route::get('subscription/activation-history', [OrganizationPanelController::class, 'subscriptionActivationHistory']);
        Route::get('subscription/activation-details', [OrganizationPanelController::class, 'subscriptionActivationDetails']);
        Route::post('subscription/redeem-promo', [OrganizationPanelController::class, 'redeemPromoForRenewal']);
    });

});
