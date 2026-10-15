<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FavouriteStreamer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KickController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();
        if (empty($user)) {
            return to_route('login');
        }

        return Inertia::render('Kick');
    }

    public function toggleFavoriteStreamer(string $streamerId, Request $request): RedirectResponse
    {
        $userId = auth()->id();
        $validated = $request->validate([
            'streamerName' => ['required', 'string'],
        ]);

        $favorite = FavouriteStreamer::query()->firstOrCreate([
            'user_id' => $userId,
            'streamer_id' => $streamerId,
        ], [
            'streamer_name' => $validated['streamerName'],
        ]);

        if (! $favorite->wasRecentlyCreated) {
            $favorite->delete();
        }

        return back(status: 303);
    }
}
