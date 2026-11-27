<?php

return [
    // ollama (local, default), gemini (used automatically when GEMINI_API_KEY is set) or fake.
    'driver' => env('AI_DRIVER', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://ollama:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 180),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'url' => 'https://generativelanguage.googleapis.com/v1beta',
    ],

    // Generation requests per brand per hour.
    'hourly_limit' => (int) env('AI_HOURLY_LIMIT', 30),
];
