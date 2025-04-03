<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserSocialAccount;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Exception\ClientException;

class SocialAuthService
{
    private User $user;
    public function __construct(private AuthService $authService)
    {
    }

    public function getRedirectUrl(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleCallback(string $provider, $socialUser)
    {
        try {
            // Find or create user
            $findSocialAccount = UserSocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())->first();

            if (!$findSocialAccount) {
                $this->user = $this->createUser($socialUser);
                $this->user->markEmailAsVerified();
                // Associate social account with user
                $this->associateSocialAccount($provider, $socialUser);
            } else {
                $this->user = $findSocialAccount->user;
                $this->user->update([
                    'password' => $socialUser->token,
                ]);
            }

            // Generate user access token and refresh token
            return $this->generateUserToken($socialUser);
        } catch (ClientException $e) {
            throw new \Exception(__('auth.invalid_or_expired_social_token'), 401);
        }
    }

    private function createUser($socialUser)
    {
        return User::create([
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'email_verified_at' => now(),
            'avatar' => $socialUser->getAvatar(),
            'password' => $socialUser->token,
        ]);
    }




    private function associateSocialAccount(string $provider, $socialUser)
    {
        $this->user->UserSocialAccounts()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ],
            [
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]
        );
    }

    private function generateUserToken($socialUser)
    {
        // Attempt login with social token
        $tokenResult = $this->authService->getTokenAndRefreshToken(
            $socialUser->getEmail(),
            $socialUser->token
        );
        dd($tokenResult);

        // remove password from user table 
        $this->user->update(['password' => null]);

        if (!$tokenResult || !isset($tokenResult['access_token'])) {
            throw new \Exception(__('auth.login_failed'), 401);
        }

        return $tokenResult;
    }
}