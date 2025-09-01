<?php

namespace App\Services;

use App\Models\User;
use Error;
use Exception;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\TwitchProvider;
use Log;
use Throwable;

class TwitchTokenManagerService
{
    const int MIN_TOKEN_VALIDITY_SECONDS = 900;

    public function __construct() {}

    /**
     * Ensures that the provided user's access tokens are fresh and valid.
     *
     * If the current access token is valid and has sufficient remaining validity,
     * it will be returned. Otherwise, the method will attempt to refresh the token
     * using the provided refresh token.
     *
     * @param  User  $user  The user whose access tokens need to be validated and possibly refreshed.
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     expires_in: int,
     * } An array containing the access token, refresh token, and expiration time in seconds.
     *
     * @throws Error|Throwable If the access token or refresh token is missing or if refreshing the tokens fails.
     */
    public function ensureFreshUserAccessTokens(User $user): array
    {
        $accessToken = $user->auth_provider_access_token;
        $refreshToken = $user->auth_provider_refresh_token;
        $expiresAt = $user->auth_provider_expires_at;

        if (empty($accessToken) || empty($refreshToken)) {
            throw new Error('Access token or refresh token is missing.');
        }

        /**
         * Token is valid if it is not expired and has at least N MIN minutes of validity.
         */
        if ($expiresAt?->gt(now()->addSeconds(self::MIN_TOKEN_VALIDITY_SECONDS))) {
            Log::debug(sprintf('[%s] Access token is still valid for user %s, expiring in %s', self::class, $user->id, $expiresAt->diffForHumans()));

            return [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt,
            ];
        }

        /** @var TwitchProvider $twitchSocialiteDriver */
        $twitchSocialiteDriver = Socialite::driver('twitch');

        try {
            $tokens = $twitchSocialiteDriver->refreshToken($refreshToken);
            if (
                empty($tokens->token) ||
                empty($tokens->refreshToken) ||
                empty($tokens->expiresIn)
            ) {
                $this->clearTokens($user);
                throw new Error('Failed to refresh tokens: missing access token, refresh token or expires time in the response.');
            }

            $user->forceFill([
                'auth_provider_access_token' => $tokens->token,
                'auth_provider_refresh_token' => $tokens->refreshToken,
                'auth_provider_expires_at' => now()->utc()->addSeconds($tokens->expiresIn),
            ])->saveOrFail();
            Log::info('Refreshed Twitch tokens for user '.$user->id);

            return [
                'access_token' => $tokens->token,
                'refresh_token' => $tokens->refreshToken,
                'expires_at' => now()->addSeconds($tokens->expiresIn),
            ];

        } catch (Exception|Throwable $e) {
            $this->clearTokens($user);
            Log::warning('Failed to refresh Twitch tokens for user '.$user->id.': '.$e->getMessage());
            throw $e;
        }
    }

    private function clearTokens(User $user): void
    {
        $user->forceFill([
            'auth_provider_access_token' => null,
            'auth_provider_refresh_token' => null,
            'auth_provider_expires_at' => null,
        ])->save();
    }
}
