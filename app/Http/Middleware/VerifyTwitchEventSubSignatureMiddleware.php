<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTwitchEventSubSignatureMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {

        $id = $request->header('Twitch-Eventsub-Message-Id');
        /** @var string|null $timestamp in RFC3339 format */
        $timestamp = $request->header('Twitch-Eventsub-Message-Timestamp');
        $signature = $request->header('Twitch-Eventsub-Message-Signature'); // "sha256=...."

        if (! $id || ! $timestamp || ! $signature) {
            $log = sprintf('[%s] Missing required headers for EventSub verification', self::class);
            Log::warning($log);
            abort(400, $log);
        }

        /**
         * Prevent replay attacks by caching the message ID for 10 minutes (max age of a message).
         */
        $cacheKey = sprintf('twitch:eventsub:%s', $id);
        if (! Cache::add($cacheKey, true, now()->addMinutes(10))) {
            $log = sprintf('[%s] Replay detected for EventSub message ID %s', self::class, $id);
            Log::warning($log);
            abort(409, $log);
        }

        if (abs(now()->diffInSeconds(Carbon::parse($timestamp))) > 600) {
            $log = sprintf('[%s] Stale timestamp detected for EventSub message ID %s', self::class, $id);
            Log::warning($log);
            abort(412, $log);
        }

        $rawBody = $request->getContent(); // Need raw body for HMAC calculation
        $rawData = sprintf('%s%s%s', $id, $timestamp, $rawBody);
        $secret = config('services.twitch.eventsub_secret');

        $computedHash = 'sha256='.hash_hmac('sha256', $rawData, $secret);

        /**
         * Use hash_equals to check HMAC signature to prevent timing attacks
         * Given signature needs to be the second argument
         */
        if (! hash_equals($computedHash, $signature)) {
            $log = sprintf('[%s] Invalid signature for EventSub message ID %s', self::class, $id);
            Log::error($log);
            abort(403, $log);
        }

        Log::debug(sprintf('[%s] Successfully verified EventSub message ID %s', self::class, $id));

        return $next($request);
    }
}
