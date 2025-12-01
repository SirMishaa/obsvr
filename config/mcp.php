<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | An "*" may be used to allow all domains.
    |
    */

    'redirect_domains' => [
        '*',
        // 'https://example.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default User ID for Local MCP Servers
    |--------------------------------------------------------------------------
    |
    | This user ID will be used as the default authenticated user for local
    | MCP server requests when no user_id parameter is explicitly provided.
    | This is particularly useful for local MCP servers that need to access
    | user-specific data without OAuth authentication.
    |
    */

    'default_user_id' => env('MCP_DEFAULT_USER_ID'),

];
