<?php

return [
    'ai_provider' => env('AI_PROVIDER', 'fake'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-terra'),
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
        'input_cost_per_million' => (float) env('OPENAI_INPUT_COST_PER_MILLION', 2.5),
        'output_cost_per_million' => (float) env('OPENAI_OUTPUT_COST_PER_MILLION', 15),
    ],
    'imap' => [
        'enabled' => (bool) env('IMAP_ENABLED', false),
        'host' => env('IMAP_HOST'),
        'port' => (int) env('IMAP_PORT', 993),
        'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
        'validate_cert' => (bool) env('IMAP_VALIDATE_CERT', true),
        'username' => env('IMAP_USERNAME'),
        'password' => env('IMAP_PASSWORD'),
        'authentication' => env('IMAP_AUTHENTICATION'),
        'folder' => env('IMAP_FOLDER', 'INBOX'),
        'timeout' => (int) env('IMAP_TIMEOUT', 30),
        'sync_since_days' => (int) env('IMAP_SYNC_SINCE_DAYS', 14),
        'max_messages' => (int) env('IMAP_MAX_MESSAGES', 50),
    ],
];
