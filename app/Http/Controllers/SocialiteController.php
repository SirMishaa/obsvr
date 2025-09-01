<?php

namespace App\Http\Controllers;

use App\Enums\AuthProvider;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JetBrains\PhpStorm\ArrayShape;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteController extends Controller
{
    const array scopes = [
        'user:read:email',
        'user:read:follows',
    ];

    public function redirect(string $provider): RedirectResponse
    {
        $this->validateOrThrow(compact('provider'));

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver
            ->scopes(self::scopes)
            ->with(['force_verify' => 'true'])
            ->redirect();
    }

    /**
     * Handles callback from an external authentication provider.
     *
     * @param  string  $provider  The authentication provider name.
     * @return \Illuminate\Http\RedirectResponse The response to redirect to the appropriate route or view.
     *
     * @throws ValidationException If the provider validation fails.
     * @throws InvalidStateException If the state of the social authentication is invalid.
     */
    public function handleCallback(string $provider)
    {
        try {

            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($provider);

            $this->validateOrThrow(compact('provider'));
            /** @var \Laravel\Socialite\Two\User $user */
            $user = $driver->user();

            /**
             * If the user has not approved the scopes or the scopes have changed,
             * we need to re-authenticate the user to make him give him his consent again.
             */
            if (array_diff(self::scopes, $user->approvedScopes ?? [])) {
                return $driver->scopes(['user:read:follows'])->with(['force_verify' => 'true'])->redirect();
            }

        } catch (InvalidStateException|ValidationException $invalidStateException) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'provider' => "Something went wrong: $invalidStateException",
                ]);
        }

        /** @var User|null $updatedOrCreatedUser */
        $updatedOrCreatedUser = null;

        try {

            $dbUser = User::where('auth_provider_id', $user->getId())
                ->where('auth_provider', AuthProvider::from($provider))
                ->first();

            $updatedOrCreatedUser = User::updateOrCreate(
                [
                    'auth_provider_id' => $user->getId(),
                    'auth_provider' => AuthProvider::from($provider),
                ], [
                    'avatar_url' => $user->getAvatar(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'password' => $dbUser?->password ?: Hash::make(openssl_random_pseudo_bytes(32)),
                ]);

            $updatedOrCreatedUser->forceFill([
                'auth_provider_access_token' => $user->token,
                'auth_provider_refresh_token' => $user->refreshToken,
                /** TTL (Time to Live) in seconds given before expiration, that's why we use `addSeconds` */
                'auth_provider_expires_at' => $user->expiresIn ? now()->utc()->addSeconds($user->expiresIn) : null,
            ])->save();

        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('auth.already_registered'),
            ]);
        }

        Auth::login($updatedOrCreatedUser, true);

        return redirect()->intended(route('twitch'));
    }

    /**
     * @throws ValidationException
     */
    private function validateOrThrow(
        #[ArrayShape([
            'provider' => 'string',
        ])]
        array $data
    ): void {
        Validator::make($data, [
            'provider' => ['required', 'string', 'in:twitch'],
        ])->validate();
    }
}
