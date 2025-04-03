<?php

namespace App\Http\Controllers\v1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Services\Auth\SocialAuthService;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{

    public function __construct(
        private AuthService $authService,
        private SocialAuthService $socialAuthService
    ) {
    }

    public function redirect($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback($provider)
    {
        try {
            DB::beginTransaction();

            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Generate social token
            $tokenResult = $this->socialAuthService->handleCallback($provider, $socialUser);

            if (!$tokenResult || !isset($tokenResult['access_token'])) {
                DB::rollBack();
                return $this->errorResponse(__('auth.login_failed'), 401);
            }

            DB::commit();
            return $this->tokenResponse($tokenResult, __('auth.login_success'), $tokenResult['refresh_token']);

        } catch (ClientException $e) {
            DB::rollBack();
            return $this->errorResponse(__('auth.invalid_or_expired_social_token'), 401);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
