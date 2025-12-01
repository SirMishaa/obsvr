<?php

namespace App\Mcp\Tools;

use App\Data\TwitchStreamData;
use App\Data\TwitchStreamPaginatedResponse;
use App\Models\User;
use App\Services\TwitchApiClient;
use App\Services\TwitchTokenManagerService;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[IsOpenWorld]
class CurrentStreamersInLive extends Tool
{
    public function __construct(private readonly TwitchTokenManagerService $tokenManagerService, private readonly TwitchApiClient $twitchApiClient) {}

    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Retrieves the list of currently live streamers from the authenticated user's followed channels on Twitch.

        This tool fetches real-time streaming data including stream titles, games being played, viewer counts,
        stream start times, and other metadata. Results are sorted by viewer count (highest first) and cached
        for optimal performance. Requires Twitch OAuth authentication.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // Get user ID from parameter or fallback to default config
        $userId = $request->get('user_id') ?? config('mcp.default_user_id');

        if (empty($userId)) {
            return Response::text('No user specified. Please provide a user_id parameter or set MCP_DEFAULT_USER_ID in your .env file.');
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (empty($user)) {
            return Response::text("User with ID $userId not found.");
        }

        try {
            $this->tokenManagerService->ensureFreshUserAccessTokens($user);
            $this->tokenManagerService->ensureFreshAppAccessToken();
        } catch (Throwable $e) {
            return Response::text('Failed to ensure fresh tokens: '.$e->getMessage());
        }

        $user->refresh();
        $streamersStatus = $this->twitchApiClient->getStatusOfFollowedStreamers($user->auth_provider_id, $user->auth_provider_access_token);

        return Response::text($this->formatStreamersList($streamersStatus));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()
                ->description('Optional user ID to fetch streams for. If not provided, uses the default user from MCP_DEFAULT_USER_ID config.')
                ->nullable(),
        ];
    }

    private function formatStreamersList(TwitchStreamPaginatedResponse $streamers): string
    {
        if ($streamers->data->count() === 0) {
            return 'No streamers are currently live.';
        }

        /** @var Collection<int, TwitchStreamData> $collection */
        $collection = $streamers->data->toCollection();

        $totalStreamers = $collection->count();
        $totalViewers = $collection->sum('viewerCount');

        // Sort by viewer count descending
        /** @var Collection<int, TwitchStreamData> $sortedStreamers */
        $sortedStreamers = $collection->sortByDesc('viewerCount');

        $formattedList = "## Currently Live Streamers ($totalStreamers)\n";
        $formattedList .= 'Total viewers: '.number_format($totalViewers)."\n\n";

        foreach ($sortedStreamers as $streamer) {
            $duration = $streamer->startedAt->diffForHumans(['short' => true]);
            $viewers = number_format($streamer->viewerCount);

            $formattedList .= "### $streamer->userName\n";
            $formattedList .= "- Title: $streamer->title\n";
            $formattedList .= "- Game: $streamer->gameName\n";
            $formattedList .= "- Viewers: $viewers\n";
            $formattedList .= "- Started: $duration\n";
            $formattedList .= "- Language: $streamer->language\n";

            if ($streamer->isMature) {
                $formattedList .= "- Mature content: Yes\n";
            }

            $formattedList .= "\n";
        }

        return $formattedList;
    }
}
