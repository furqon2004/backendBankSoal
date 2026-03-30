<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI service used to generate quiz questions.
    | Currently supports Google Gemini API.
    |
    */

    'provider' => env('AI_PROVIDER', 'gemini'),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
        'temperature' => 0.7,
        'max_tokens' => 8192,
    ],

    'questions' => [
        'min_count' => 23,
        'max_count' => 32,
        'default_count' => 28,
    ],
];
