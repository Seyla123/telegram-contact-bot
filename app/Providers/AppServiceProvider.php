<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\User;
use App\Observers\MessageObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
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
        // Passport
        Passport::ignoreRoutes();
        Passport::enablePasswordGrant();

        // Register the observer
        User::observe(UserObserver::class);
        Message::observe(MessageObserver::class);
    }
}
