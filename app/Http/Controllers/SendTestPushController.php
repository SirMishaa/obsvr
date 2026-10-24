<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PushTestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SendTestPushController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->notifyNow(new PushTestNotification);

        return back(status: 303);
    }
}
