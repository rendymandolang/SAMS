<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SAMS Language Configuration
    |--------------------------------------------------------------------------
    |
    | SAMS intentionally supports only Indonesian and English. Keeping the
    | language catalogue explicit prevents an unsupported locale from being
    | selected through a URL or a modified browser session.
    |
    */

    'default' => env('APP_LOCALE', 'id'),

    'session_key' => 'locale',

    'supported' => [
        'id' => [
            'name' => 'Bahasa Indonesia',
            'short_name' => 'ID',
        ],
        'en' => [
            'name' => 'English',
            'short_name' => 'EN',
        ],
    ],
];
