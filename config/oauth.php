<?php

use App\Helpers\OAuthTwitter;

return [
    'key' => [
        'twitter' => [
            'key' => env( 'TWITTER_API_KEY' ),
            'secret' => env( 'TWITTER_API_SECRET' ),
        ],
    ],
    'driver' => [
        'twitter' => [
            'class' => OAuthTwitter::class,
            'method' => [
                'getAuthUrl',
                'getUserData',
            ],
        ]
    ],

];
