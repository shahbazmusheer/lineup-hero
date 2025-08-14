<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebPaymentController; // Ensure this is imported
use App\Models\Settings;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    $settings = Settings::instance();
    return view('welcome', ['adminMail' => $settings->admin_notification_email]); // Your application's welcome/landing page
});

// NEW: For a User RENEWING a specific independent team's activation
Route::get('/renew-team-activation/{team}', [WebPaymentController::class, 'showDirectTeamRenewalPage'])
    ->name('team.payment.initiate.renewal') // New route name
    ->middleware('signed');

// --- Web Payment Flow Routes (Using Signed URLs, No Laravel Web Auth Session needed here) ---

// For a User purchasing a "Team Activation Slot" (Path A for independent teams)
Route::get('/pay-for-team-activation-slot/{user}', [WebPaymentController::class, 'showTeamActivationSlotPage'])
    ->name('team_activation_slot.payment.initiate.web') // Route name for generating signed URL
    ->middleware('signed'); // Protects against URL tampering

// For an Organization Admin RENEWING their Organization's subscription
Route::get('/pay-for-organization-renewal/{organization}', [WebPaymentController::class, 'showOrganizationRenewalPage'])
    ->name('organization.payment.initiate.renewal') // Route name for generating signed URL
    ->middleware('signed'); // Protects against URL tampering

// Generic Return URL from Stripe (can be used by both payment flows)
// This page just displays a message; actual activation/renewal via webhook.
Route::get('/payment/return', [WebPaymentController::class, 'handleGenericReturn'])
    ->name('payment.return.general');

// If you have Laravel's default auth scaffolding and want to keep it for web admins:
// Auth::routes(['register' => false]); // Example: disable self-registration for web admin
// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');