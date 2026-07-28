<?php

use App\Enums\EnvironmentEnum;
use App\Enums\ProviderEnum;

return [

    'services' => [
        'userback' => [
            'url' => env('USERBACK_URL', 'https://static.userback.io/widget/v1.js'),
            'token' => env('USERBACK_TOKEN'),
            'environments' => [
                EnvironmentEnum::STAGING->value,
                EnvironmentEnum::PRODUCTION->value,
            ],
        ],
        'fathom' => [
            'url' => env('FATHOM_URL', 'https://cdn-eu.usefathom.com/script.js'),
            'site_id' => env('FATHOM_SITE_ID'),
            'environments' => [
                EnvironmentEnum::STAGING->value,
                EnvironmentEnum::PRODUCTION->value,
            ],
        ],
    ],

    'auth' => [
        'providers' => [
            ProviderEnum::MICROSOFT_OFFICE_365,
        ],
    ],
];
