<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Raster basemap tiles for every Leaflet map (public site + admin pin
    // picker). Since 25 Sep 2026 CARTO watermarks keyless tiles with
    // "API KEY REQUIRED"; a free key comes from https://carto.com/basemaps/apikey
    //
    // The studio's key is the default here, not only in .env: the host has no
    // shell to edit .env, and the key is public anyway — it rides on every
    // tile URL a visitor's browser requests. CARTO_BASEMAPS_KEY overrides it.
    'carto' => [
        'basemaps_key' => env('CARTO_BASEMAPS_KEY') ?: 'cb1_4hfu_1_0a6f7272722fdd9b2a701031',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
