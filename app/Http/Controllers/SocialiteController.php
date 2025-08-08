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

class SocialiteController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        $this->validateOrThrow(compact('provider'));

        return Socialite::driver($provider)->redirect();
    }

    public function handleCallback(string $provider)
    {
        $this->validateOrThrow(compact('provider'));

        $user = Socialite::driver($provider)->user();

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
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('auth.already_registered'),
            ]);
        }

        Auth::login($updatedOrCreatedUser, true);

        return redirect()->intended(route('dashboard'));
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
