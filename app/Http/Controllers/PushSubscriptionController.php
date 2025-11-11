<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    public function __invoke(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
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

            Log::debug(sprintf('Updating push subscription for user %s (%s)', $user->id, $user->email));
        }

        return back(status: 303);
    }
}
