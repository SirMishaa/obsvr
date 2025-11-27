<?php

namespace Tests\Mocks;

class TwitchApiResponses
{
    /**
     * Mock response for followed channels endpoint
     */
    public static function followedChannels(): array
    {
        return [
            'total' => 1,
            'data' => [
                [
                    'broadcaster_id' => '123',
                    'broadcaster_login' => 'mockedstreamer',
                    'broadcaster_name' => 'MockedStreamer',
                    'followed_at' => '2024-01-01T00:00:00Z',
                ],
            ],
            'pagination' => [],
        ];
    }

    /**
     * Mock response for followed streams endpoint
     */
    public static function followedStreams(): array
    {
        return [
            'data' => [
                [
                    'id' => 'stream-1',
                    'user_id' => '123',
                    'user_login' => 'livestreamer',
                    'user_name' => 'LiveStreamer',
                    'game_id' => '509658',
                    'game_name' => 'Just Chatting',
                    'type' => 'live',
                    'title' => 'Test Stream',
                    'viewer_count' => 1000,
                    'started_at' => now()->subHour()->toIso8601String(),
                    'thumbnail_url' => 'https://example.com/{width}x{height}.jpg',
                    'is_mature' => false,
                    'language' => 'en',
                    'tag_ids' => [],
                    'tags' => [],
                ],
            ],
            'pagination' => [],
        ];
    }

    /**
     * Mock response for EventSub subscriptions endpoint
     */
    public static function eventSubSubscriptions(): array
    {
        return [
            'data' => [],
            'total' => 0,
            'total_cost' => 0,
            'max_total_cost' => 10000,
            'pagination' => [],
        ];
    }

    /**
     * Mock response for Twitch OAuth token endpoint (app token)
     */
    public static function appAccessToken(): array
    {
        return [
            'access_token' => 'mock-app-access-token',
            'expires_in' => 5184000, // 60 days
            'token_type' => 'bearer',
        ];
    }

    /**
     * Mock response for Twitch OAuth refresh token endpoint (user token)
     */
    public static function refreshToken(): array
    {
        return [
            'access_token' => 'mock-refreshed-user-token',
            'refresh_token' => 'mock-new-refresh-token',
            'expires_in' => 14400, // 4 hours
            'scope' => ['user:read:email', 'user:read:follows'],
            'token_type' => 'bearer',
        ];
    }
}
