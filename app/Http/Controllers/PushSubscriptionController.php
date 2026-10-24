<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class PushSubscriptionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'previousEndpoint' => ['nullable', 'string'],
        ]);

        $user = Auth::user();

        // contentEncoding: the library usually deduces this, but you can explicitly pass ‘aesgcm’ or ‘aes128gcm’ if necessary.
        if ($user) {
            $user->updatePushSubscription(
                endpoint: $validated['endpoint'],
                key: $validated['keys']['p256dh'],
                token: $validated['keys']['auth'],
                contentEncoding: $request->header('Content-Encoding') ?? null
            );

            if (isset($validated['previousEndpoint']) && $validated['previousEndpoint'] !== $validated['endpoint']) {
                $user->deletePushSubscription($validated['previousEndpoint']);
            }

            Log::debug(sprintf('Updating push subscription for user %s (%s)', $user->id, $user->email));
        }

        return back(status: 303);
    }
}
