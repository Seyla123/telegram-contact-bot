<?php

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Passport\Client as OClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

/**
 * Class AuthService
 * 
 * Handles all authentication related operations including OAuth token management,
 * user registration, login, password reset and user session management.
 */
class AuthService
{
    /**
     * @var OClient
     * OAuth client instance for handling token operations
     */
    protected OClient $client;

    /**
     * Initialize the auth service with OAuth client
     * 
     * @throws \RuntimeException when OAuth client is not found
     */
    public function __construct()
    {
        $this->client = OClient::where('password_client', 1)->first();
        if (!$this->client) {
            throw new \RuntimeException('OAuth client not found');
        }
    }

    /**
     * Get OAuth token and refresh token for user credentials
     * 
     * @param string $email User email
     * @param string $password User password
     * @return array Token response containing access and refresh tokens
     */
    public function getTokenAndRefreshToken(string $email, string $password): array
    {
        $params = [
            'grant_type' => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'username' => $email,
            'password' => $password,
            'scope' => '*'
        ];

        return $this->makeTokenRequest($params);
    }

    /**
     * Refresh an existing OAuth token
     * 
     * @param string $refreshToken The refresh token to use
     * @return array New token response
     */
    public function refreshToken(string $refreshToken): array
    {
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'scope' => '*',
        ];

        return $this->makeTokenRequest($params);
    }

    /**
     * Revoke both access and refresh tokens
     * 
     * @param string $tokenId The token ID to revoke
     */
    public function revokeToken(string $tokenId): void
    {
        $tokenRepository = app(TokenRepository::class);
        $refreshTokenRepository = app(RefreshTokenRepository::class);

        // Revoke access token
        $tokenRepository->revokeAccessToken($tokenId);

        // Revoke refresh token
        $refreshTokenRepository->revokeRefreshToken($tokenId);
    }

    /**
     * Make a token request to the OAuth server
     * 
     * @param array $params Parameters for the token request
     * @return array Decoded response from the OAuth server
     * 
     * This method handles both password grant and refresh token requests:
     * 1. Adds parameters to the current request
     * 2. Creates a new request to oauth/token endpoint
     * 3. Dispatches the request through Laravel's router
     * 4. For password grants, if refresh token is missing, updates client and retries
     */
    protected function makeTokenRequest(array $params): array
    {
        request()->request->add($params);
        $request = Request::create('oauth/token', 'POST');

        $response = Route::dispatch($request);
        $result = json_decode($response->getContent(), true);

        if (!isset($result['refresh_token']) && $params['grant_type'] === 'password') {
            $this->client->update(['personal_access_client' => true]);
            $response = Route::dispatch($request);
            $result = json_decode($response->getContent(), true);
        }

        return $result;
    }

    /**
     * Attempt to log in a user and return OAuth tokens
     * 
     * @param array $credentials User login credentials
     * @return array|false Token response or false if login fails
     */
    public function attemptLogin(array $credentials): array|false
    {
        // Check if user exists
        // $user = User::where('email', $credentials['email'])->where('password', "!=", null)->first();
        // if ($user) {
        //     dd($user);
        //     return false;
        // }

        if (Auth::attempt($credentials)) {

            return $this->getTokenAndRefreshToken($credentials['email'], $credentials['password']);
        }
        
        return false;
    }

    /**
     * Register a new user
     * 
     * @param array $userData User registration data
     * @return User Newly created user instance
     */
    public function register(array $userData): User
    {
        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => bcrypt($userData['password'])
        ]);

        event(new Registered($user));

        return $user;
    }

    /**
     * Send password reset link to user's email
     * 
     * @param string $email User email
     * @return bool Whether reset link was sent successfully
     */
    public function forgotPassword(string $email): bool
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            return false;
        }

        $status = Password::sendResetLink(['email' => $email]);
        return $status === Password::RESET_LINK_SENT;
    }

    /**
     * Reset user's password
     * 
     * @param array $data Password reset data
     * @return bool Whether password was reset successfully
     */
    public function resetPassword(array $data): bool
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET;
    }

    /**
     * Log out the user by revoking their tokens
     * 
     * @param string $tokenId The token ID to revoke
     * @return bool Whether logout was successful
     */
    public function logout(string $tokenId): bool
    {
        try {
            $this->revokeToken($tokenId);
            return true;
        } catch (\Throwable $th) {
            Log::error("Token revocation failed: " . $th->getMessage());
            return false;
        }
    }
}