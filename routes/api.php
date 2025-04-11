<?php

use App\Http\Controllers\v1\Auth\AuthController;
use App\Http\Controllers\v1\Auth\SocialAuthController;
use App\Http\Controllers\v1\Auth\VerifyEmailController;
use App\Http\Controllers\v1\Profile\ProfileController;
use App\Http\Controllers\v1\Telegram\TelegramController;
use Illuminate\Support\Facades\Route;

/**
 * ┌──────────────────────┐
 * │ API Version 1        │
 * └──────────────────────┘
 * 
 * This file contains all API routes for version 1
 * All routes are prefixed with /api/v1
 */
Route::prefix('v1')->group(function () {

    /**
     * ┌──────────────────────┐
     * │ Authentication       │
     * └──────────────────────┘
     * 
     * Base route: /v1/auth/
     * Handles user authentication operations
     */
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');

        // Email verification routes
        Route::prefix('email')->group(function () {
            Route::get('/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])->name('verification.verify');
            Route::post('/verify/resend', [VerifyEmailController::class, 'resend'])->middleware('auth:api', 'throttle:6,1');
        });

        // Social login routes
        Route::prefix('social')->group(function () {
            Route::get('{provider}', [SocialAuthController::class, 'redirect']);
            Route::get('{provider}/callback', [SocialAuthController::class, 'callback']);
        });

    });

    /**
     * ┌──────────────────────┐
     * │ Protected Routes     │
     * └──────────────────────┘
     * 
     * Routes that require authentication and email verification
     */
    Route::middleware(['auth:api', 'verified'])->group(function () {
        Route::prefix('user')->group(function () {
            /**
             * ┌──────────────────────┐
             * │ User Profile         │
             * └──────────────────────┘
             * 
             * Base route: /v1/user/profile
             * Handles user profile operations:
             */
            Route::prefix('profile')->group(function () {
                Route::get('', [ProfileController::class, 'getProfile']);
                Route::post('', [ProfileController::class, 'updateProfile']);
                // Route for updating the user's avatar, e.g., '/v1/user/profile/avatar
                Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
            });
        });

        /**
         * ┌──────────────────────┐
         * │ Telegram Routes      │
         * └──────────────────────┘
         * 
         */
    });
    Route::prefix('telegram')->group(function () {

        // config webhook
        Route::post('setWebhook', [TelegramController::class, 'setWebhook']);

        Route::get('getFile/{fileId}', [TelegramController::class, 'getFile']);
        Route::get('update', [TelegramController::class, 'telegramUpdate']);

        Route::get('bot', [TelegramController::class, 'getBot']);
        Route::post('webhook', [TelegramController::class, 'webhook']);
        Route::get('contact', [TelegramController::class, 'getAllContact']);
        Route::get('message', [TelegramController::class, 'getAllMessage']);
        Route::post('message', [TelegramController::class, 'sendMessage']);
        Route::post('sendPhoto', [TelegramController::class, 'sendPhoto']);

        // for test me
    });
});
