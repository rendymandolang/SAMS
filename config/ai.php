<?php

return [
    'driver' => env('AI_DRIVER', 'local'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
    ],
];
