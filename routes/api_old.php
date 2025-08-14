<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Auth Controllers ---
use App\Http\Controllers\Api\V1\Auth\UniversalLoginController;
use App\Http\Controllers\Api\V1\Auth\UserAuthController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;

// --- User-Facing & General Controllers ---
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\PlayerController;
use App\Http\Controllers\Api\V1\PlayerPreferenceController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\PromoCodeController as UserActionPromoCodeController;
use App\Http\Controllers\Api\V1\StripeController;
use App\Http\Controllers\Api\V1\OrganizationController as UserFacingOrganizationController;
use App\Http\Controllers\Api\V1\PositionController as PositionController;
// use App\Http\Controllers\Api\V1\AppConfigController; // Keep if used

// --- Admin Resource Management Controllers ---
use App\Http\Controllers\Api\V1\Admin\AdminOrganizationController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminPromoCodeController;
use App\Http\Controllers\Api\V1\Admin\AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\AdminUtilityController;

// --- Organization Panel Controller ---
use App\Http\Controllers\Api\V1\Organization\OrganizationPanelController;

/* API Routes V1 */
Route::prefix('v1')->group(function () {

    // --- PUBLIC ---
    Route::post('auth/login', [UniversalLoginController::class, 'login']);
    Route::prefix('user/auth')->controller(UserAuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('forgot-password', 'forgotPassword');
        Route::post('reset-password', 'resetPassword');
    });
    Route::post('organization-panel/auth/login', [OrganizationPanelController::class, 'login']);
    Route::post('stripe/webhook', [StripeController::class, 'handleWebhook'])->name('stripe.webhook.api');
    // Route::get('app-config', [AppConfigController::class, 'getPublicConfig']);


    // --- USER AUTHENTICATED (auth:api_user) ---
    Route::middleware('auth:api_user')->group(function () {
        Route::prefix('user/auth')->controller(UserAuthController::class)->group(function () {
            Route::post('logout', 'logout'); Route::post('refresh', 'refresh');
            Route::get('profile', 'profile'); Route::put('profile', 'updateProfile');
            Route::post('change-password', 'changePassword');
        });

        // Team Management (Path A for independent, Path B for org-linked)
        Route::apiResource('teams', TeamController::class);
        Route::get('teams/{team}/players', [TeamController::class, 'listPlayers']);

        // Path A: Direct Team Activation (User pays/promos for a "team activation slot" THEN creates team using it)
        Route::post('user/team-activation-slots/create-payment-intent', [StripeController::class, 'createTeamActivationSlotPaymentIntent']);
        Route::get('user/team-activation-slots/generate-payment-link', [StripeController::class, 'generateTeamActivationSlotWebLink']);
        Route::post('promo-codes/redeem', [UserActionPromoCodeController::class, 'redeem']); // New specific route

        // Player & Preferences
        Route::post('teams/{team}/players', [PlayerController::class, 'store']);
        Route::get('players/{player}', [PlayerController::class, 'show']);
        Route::put('players/{player}', [PlayerController::class, 'update']);
        Route::delete('players/{player}', [PlayerController::class, 'destroy']);
        Route::post('players/{player}/preferences', [PlayerPreferenceController::class, 'store']);
        Route::get('players/{player}/preferences', [PlayerPreferenceController::class, 'show']);
        Route::get('teams/{team}/bulk-player-preferences', [PlayerPreferenceController::class, 'bulkShowByTeam']);
        Route::put('teams/{team}/bulk-player-preferences', [PlayerPreferenceController::class, 'bulkUpdateByTeam']);

        // Game, Lineup, PDF
        Route::get('teams/{team}/games', [GameController::class, 'index']);
        Route::post('teams/{team}/games', [GameController::class, 'store']);
        Route::apiResource('games', GameController::class)->except(['index', 'store']);
        Route::get('games/{game}/lineup', [GameController::class, 'getLineup']);
        Route::put('games/{game}/lineup', [GameController::class, 'updateLineup']);
        Route::post('games/{game}/autocomplete-lineup', [GameController::class, 'autocompleteLineup']);
        Route::get('games/{game}/pdf-data', [GameController::class, 'getLineupPdfData']); // PDF access is now always allowed for owner

        // History & Supporting
        Route::get('payments/history', [StripeController::class, 'userPaymentHistory']);
        Route::get('promo-codes/redemption-history', [UserActionPromoCodeController::class, 'redemptionHistory']);
        Route::get('activation-history', [UserAuthController::class, 'activationHistory']);
        Route::get('organizations', [UserFacingOrganizationController::class, 'index']);
        Route::get('organizations/by-code/{organization_code}', [UserFacingOrganizationController::class, 'showByCode']);
        Route::get('positions', [PositionController::class, 'index']);
        Route::get('payment-details', [StripeController::class, 'getPaymentDetails']); // Global payment price
    });

    // --- SUPER ADMIN AUTHENTICATED (auth:api_admin) ---
    Route::prefix('admin')->middleware('auth:api_admin')->group(function () {
        Route::prefix('auth')->controller(AdminAuthController::class)->group(function() {
            Route::post('logout', 'logout');
            Route::post('refresh', 'refresh');
            Route::get('profile', 'profile');
            Route::put('profile', 'updateProfile');
            Route::post('change-password', 'changePassword');
        });
        Route::apiResource('organizations', AdminOrganizationController::class);
        Route::apiResource('positions', PositionController::class);
        Route::apiResource('users', AdminUserController::class);
        Route::apiResource('promo-codes', AdminPromoCodeController::class);
        Route::get('payments', [AdminPaymentController::class, 'index']);
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show']);
        Route::get('settings', [AdminSettingsController::class, 'show']);
        Route::put('settings', [AdminSettingsController::class, 'update']);
        Route::prefix('utils')->controller(AdminUtilityController::class)->group(function () {
            Route::post('migrate-and-seed', 'migrateAndSeed');
            Route::post('migrate-fresh-and-seed', 'migrateFreshAndSeed');
        });
    });

    // --- ORGANIZATION PANEL AUTHENTICATED (auth:api_org_admin) ---
    Route::prefix('organization-panel')->middleware('auth:api_org_admin')->group(function () {
        Route::post('auth/logout', [OrganizationPanelController::class, 'logout']);
        Route::get('profile', [OrganizationPanelController::class, 'profile']);
        Route::post('auth/change-password', [OrganizationPanelController::class, 'changePassword']);
        Route::get('teams', [OrganizationPanelController::class, 'listTeams']);
        Route::get('teams/{team}', [OrganizationPanelController::class, 'showTeam']);
        Route::delete('teams/{team}', [OrganizationPanelController::class, 'deleteTeam']);
        Route::post('subscription/create-renewal-intent', [OrganizationPanelController::class, 'createSubscriptionRenewalIntent']);
        Route::get('subscription/generate-renewal-link', [OrganizationPanelController::class, 'generateWebRenewalLink']);
        Route::post('subscription/redeem-promo', [OrganizationPanelController::class, 'redeemPromoForRenewal']);
        Route::get('subscription/activation-history', [OrganizationPanelController::class, 'subscriptionActivationHistory']);
    });
});
