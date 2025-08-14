<?php

namespace App\Providers;

use App\Models\Game;
use App\Policies\GamePolicy;
use App\Models\Team;          // <-- Import Team
use App\Policies\TeamPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    protected $policies = [
        // Register other policies here if you have them
        Game::class => GamePolicy::class, // <-- ADD THIS MAPPING
        Team::class => TeamPolicy::class, // <-- ADD THIS MAPPING
    ];
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
