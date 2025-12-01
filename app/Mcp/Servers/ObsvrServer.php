<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CurrentStreamersInLive;
use Laravel\Mcp\Server;

class ObsvrServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Obsvr Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        # Obsvr MCP Server

        This is the Obsvr MCP server, which provides tools to interact with Twitch streaming data and manage favorite streamers.

        ## Available Functionality

        — **Current Streamers In Live**: Retrieve the list of currently online streamers from followed channels
        — **Streamer Status**: Check the live status of followed streamers on Twitch (Not implemented yet)
        — **Favorite Streamers Management**: Access and manage the user's favorite streamers list (Not implemented yet)
        — **Stream Events**: Retrieve historical events for specific streamers (stream start, channel updates, etc.) (Not implemented yet)
        — **Followed Channels**: Get the list of channels followed by the authenticated user (Not implemented yet)

        ## Usage Guidelines

        — All tools require proper authentication via Twitch OAuth
        — Streamer data is fetched in real-time from the Twitch API
        — Use appropriate tools to minimize API calls and improve performance
        — When querying stream events, results are limited to the most recent 50 events per streamer
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        CurrentStreamersInLive::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
