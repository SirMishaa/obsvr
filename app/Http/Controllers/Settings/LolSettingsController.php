<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class LolSettingsController extends Controller
{
    /**
     * @var list<int>
     */
    private const array REMINDER_OPTIONS = [5, 10, 15, 20, 30, 60];

    public function edit(): Response
    {
        /** @var User $user */
        $user = auth()->user();

        return Inertia::render('settings/Lol', [
            'reminderMinutes' => $user->lol_reminder_minutes,
            'reminderOptions' => self::REMINDER_OPTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'reminder_minutes' => ['present', 'nullable', 'integer', Rule::in(self::REMINDER_OPTIONS)],
        ]);

        /** @var User $user */
        $user = auth()->user();
        $user->update([
            'lol_reminder_minutes' => $request->filled('reminder_minutes') ? $request->integer('reminder_minutes') : null,
        ]);

        return back(status: 303);
    }
}
