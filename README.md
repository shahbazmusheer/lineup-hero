# Lineup Hero API

Lineup Hero API is the backend service powering the Lineup Hero application, a tool for creating and managing baseball and softball team lineups, optimizing player positions, and generating PDF lineup cards. This API is built with Laravel 11 and uses JWT for multi-auth (User and Admin).

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
    - [1. Clone Repository](#1-clone-repository)
    - [2. Install Dependencies](#2-install-dependencies)
    - [3. Environment Setup](#3-environment-setup)
    - [4. Generate Application Key](#4-generate-application-key)
    - [5. Database Migration & Seeding](#5-database-migration--seeding)
    - [6. Configure JWT](#6-configure-jwt)
    - [7. Configure Stripe](#7-configure-stripe)
    - [8. Configure Python Optimizer Service (If Used)](#8-configure-python-optimizer-service-if-used)
    - [9. Configure Mail](#9-configure-mail)
    - [10. Set Up Queue Worker (Recommended)](#10-set-up-queue-worker-recommended)
    - [11. Configure Web Server](#11-configure-web-server)
- [API Endpoints](#api-endpoints)
    - [Authentication (User & Admin)](#authentication-user--admin)
    - [User Routes](#user-routes)
    - [Admin Routes](#admin-routes)
    - [Stripe Webhook](#stripe-webhook)
- [Python Lineup Optimizer Service](#python-lineup-optimizer-service)
- [Testing](#testing)
- [Deployment Notes](#deployment-notes)
- [Contributing](#contributing)
- [License](#license)

## Features

*   **Multi-Authentication:** Separate login and functionalities for Users (Coaches/Managers) and Admins using JWT.
*   **Team Management:** Create, update, list, and delete teams with details like sport type, age group, season, etc.
*   **Player Management:** Add, update, list, and delete players within teams.
*   **Player Preferences:** Set preferred and restricted positions for players.
*   **Game Management:** Create, update, list, and delete games.
*   **Lineup Builder:**
    *   Save and retrieve lineup assignments (player, position, inning).
    *   **Automated Lineup Completion:** Endpoint to call an external Python optimization service for optimal player placement.
*   **Player Statistics:** Calculation of historical player stats (`% innings played`, `top_position`, `avg_batting_loc`, etc.).
*   **PDF Data Generation:** API endpoint to provide structured JSON data for client-side (Flutter) PDF lineup card generation.
*   **Access Control for PDF Data:**
    *   Teams require active access (via payment or promo code) to enable PDF data retrieval.
    *   Access is granted for a configurable annual duration.
*   **Stripe Payment Integration:**
    *   Create Payment Intents for users to pay for team access.
    *   Webhook handler to process successful payments and update team access status.
*   **Promo Code System:**
    *   Admin management of promo codes (create, list, update, delete).
    *   User redemption of promo codes to activate team access.
*   **Application Settings:** Admin-configurable global settings (e.g., optimizer URL, prices, notification preferences).
*   **Email Notifications:**
    *   User welcome email.
    *   Password reset OTP email.
    *   Password changed notification email (for User & Admin).
    *   Payment success/failure notifications (for User).
    *   Admin notification for new payments (configurable).
*   **Admin Utilities:** API endpoints for admins to run migrations and seeders in controlled environments.

## Tech Stack

*   **Backend Framework:** Laravel 11 (PHP 8.2+)
*   **Database:** MySQL (Recommended) / PostgreSQL / SQLite (for local dev)
*   **Authentication:** `tymon/jwt-auth` for JWT
*   **Payments:** Stripe PHP SDK
*   **Lineup Optimization:** External Python (Flask + PuLP) service (optional, can be replaced by heuristics)
*   **Mail:** Laravel Mail (configurable drivers like SMTP, Mailgun, Log, Mailtrap)
*   **Queues:** Laravel Queues (Recommended for mailing, potentially other background tasks)

## Prerequisites

*   PHP >= 8.2
*   Composer
*   Database Server (MySQL >= 5.7 or MariaDB >= 10.3, or PostgreSQL >= 10)
*   Web Server (Nginx or Apache)
*   Node.js & NPM/Yarn (if you plan to modify frontend assets, less critical for pure API)
*   (If using Python Optimizer) Python >= 3.9, Pip, and a PuLP solver like CBC.
*   Stripe Account (for payment integration)

## Installation

### 1. Clone Repository

```bash
git clone https://github.com/shahbazvidicraze/lineup-hero.git lineup-hero-api
cd lineup-hero-api
```

2. Install Dependencies
```bash
composer install --optimize-autoloader --no-dev # For production
# OR
composer install # For development
```
3. Environment Setup
Copy the example environment file:
```bash
cp .env.example .env
```

Edit .env and configure the following:
APP_NAME, APP_ENV, APP_DEBUG, APP_URL
DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
MAIL_... variables for email sending.
STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET (use test keys for development).
OPTIMIZER_SERVICE_URL (URL of your Python lineup optimizer service if used).
Queue driver (QUEUE_CONNECTION), e.g., database or redis.
4. Generate Application Key
```bash
php artisan key:generate
```
5. Database Migration & Seeding
Run Migrations: Create the database schema.
```bash
php artisan migrate
```
Run Seeders: 
Populate the database with initial data (positions, settings, optional sample users/teams).
```bash
php artisan db:seed
```
This will run DatabaseSeeder.php, which should call PositionSeeder, SettingsSeeder, and OrganizationTeamPlayerSeeder.
The OrganizationTeamPlayerSeeder is designed to use existing users (e.g., created by User::factory() in DatabaseSeeder or your initial user setup) and build data for them.
6. Configure JWT
Publish the JWT configuration (if not already done when installing tymon/jwt-auth):
```bash
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```
Generate JWT Secret Key:
```bash
php artisan jwt:secret
```
Ensure your config/auth.php has the api_user and api_admin guards and providers correctly configured for JWT and your User and Admin models.
7. Configure Stripe
Add your Stripe Publishable Key (pk_...) and Secret Key (sk_...) to .env.
For local development, set up stripe listen to forward webhooks and get a local webhook signing secret for STRIPE_WEBHOOK_SECRET.
For production, create a webhook endpoint in your Stripe dashboard and use the live webhook signing secret.
Ensure the webhook route (/stripe/webhook or /api/v1/stripe/webhook) is excluded from CSRF protection in app/Http/Middleware/VerifyCsrfToken.php (Laravel <11) or bootstrap/app.php (Laravel 11+).
8. Configure Python Optimizer Service (If Used)
Set up and run your Python Flask/PuLP service (see separate deployment guide for the Python service).
Ensure the OPTIMIZER_SERVICE_URL in your Laravel .env file points to the correct URL of this running Python service.
9. Configure Mail
Set up your mail driver and credentials in .env (e.g., SMTP, Mailgun, SES, Mailtrap for testing, or log driver).
10. Set Up Queue Worker (Recommended)
For sending emails and other background tasks, set up a queue worker:
```bash
php artisan queue:work
```
For production, use a process manager like Supervisor to keep the queue worker running.
11. Configure Web Server
Document Root: Point your web server's document root to the /public directory of your Laravel project.
Rewrite Rules: Ensure proper rewrite rules are in place for Laravel (e.g., for Nginx or Apache .htaccess).
Nginx Example Snippet:
```
server {
    # ... other server config ...
    root /path/to/your/lineup-hero-api/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # ... fastcgi_pass to your PHP-FPM socket/port ...
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## API Endpoints
All User and Admin routes (except public auth routes) require a JWT Bearer token in the Authorization header.
Refer to the separate "Lineup Hero API Documentation for Flutter Client" PDF/Markdown for detailed request/response examples for each endpoint.
### Authentication (User & Admin)
* User Register: POST /user/auth/register
* User Login: POST /user/auth/login
* Admin Login: POST /admin/auth/login
* (Universal Login if implemented): POST /auth/login
* User/Admin Logout: POST /user/auth/logout, POST /admin/auth/logout
* User/Admin Refresh Token: POST /user/auth/refresh, POST /admin/auth/refresh
* User/Admin Get Profile: GET /user/auth/profile, GET /admin/auth/profile
* User/Admin Update Profile: PUT /user/auth/profile, PUT /admin/auth/profile
* User/Admin Change Password: POST /user/auth/change-password, POST /admin/auth/change-password
* User Forgot Password: POST /user/auth/forgot-password
* User Reset Password: POST /user/auth/reset-password
### User Routes
* Teams: GET, POST /teams; GET, PUT, DELETE /teams/{teamId}
* Players (Team context): POST /teams/{teamId}/players; GET /teams/{teamId}/players
* Players (Direct): GET, PUT, DELETE /players/{playerId}
* Player Preferences: POST, GET /players/{playerId}/preferences; GET, PUT /teams/{teamId}/bulk-player-preferences
* Games (Team context): GET, POST /teams/{teamId}/games
* Games (Direct): GET, PUT, DELETE /games/{gameId}
* Lineups: GET, PUT /games/{gameId}/lineup; POST /games/{gameId}/autocomplete-lineup
* PDF Data: GET /games/{gameId}/pdf-data (Requires active team access)
* Payment/Promo: POST /teams/{teamId}/create-payment-intent; POST /promo-codes/redeem
* Payment History: GET /payments/history
* Supporting Lists: GET /organizations; GET /positions
* Payment Details: GET /payment-details
### Admin Routes
(All prefixed with /admin)
* Organizations: GET, POST /organizations; GET, PUT, DELETE /organizations/{orgId}
* Positions: GET, POST /positions; GET, PUT, DELETE /positions/{posId}
* Users (Manage Coaches): GET, POST /users; GET, PUT, DELETE /users/{userId}
* Promo Codes: GET, POST /promo-codes; GET, PUT, DELETE /promo-codes/{promoId}
* Payments: GET /payments; GET /payments/{paymentId} (with filters)
* Settings: GET, PUT /settings
* Utilities: POST /utils/migrate-and-seed; POST /utils/migrate-fresh-and-seed
### Stripe Webhook
* POST /stripe/webhook (Path depends on your VerifyCsrfToken exclusion and Stripe Dashboard config)
### Python Lineup Optimizer Service
* This API relies on an external Python (Flask + PuLP) service for the "Autocomplete Lineup" feature.
* This service needs to be deployed and running independently.
* The URL for this service is configured via the OPTIMIZER_SERVICE_URL in the Laravel .env file (managed via the Admin Settings API).
* Refer to the Python service's own documentation/README for its deployment.
### Testing
* PHPUnit: Run unit and feature tests:
```bash
php artisan test
```
* Postman: A Postman collection can be used to test API endpoints. (Consider creating and sharing one).
* Stripe CLI: Use stripe listen --forward-to <your-local-webhook-url> for local webhook testing. Use stripe trigger <event> to simulate Stripe events.
### Deployment Notes
* Ensure APP_ENV=production and APP_DEBUG=false in your production .env.
* Use live Stripe keys and webhook secrets in production.
* Configure a robust queue worker (e.g., Supervisor + Redis/Database queue driver) for production.
* Set correct file permissions for storage and bootstrap/cache.
* Set up HTTPS (SSL certificate) for your domain.
* Deploy the Python optimizer service separately and ensure its URL is correctly configured in Laravel.
