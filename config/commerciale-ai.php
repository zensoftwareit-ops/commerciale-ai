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
        'timeout' => (int) env('IMAP_TIMEOUT', 30),
        'sync_since_days' => (int) env('IMAP_SYNC_SINCE_DAYS', 14),
        'max_messages' => (int) env('IMAP_MAX_MESSAGES', 50),
    ],
    'automation' => [
        'external_send_enabled' => (bool) env('AUTOMATION_EXTERNAL_SEND_ENABLED', false),
        'delivery_max_attempts' => (int) env('AUTOMATION_DELIVERY_MAX_ATTEMPTS', 3),
        'retry_base_minutes' => (int) env('AUTOMATION_RETRY_BASE_MINUTES', 5),
    ],
    'security' => [
        'platform_2fa_required' => (bool) env('PLATFORM_2FA_REQUIRED', false),
    ],
    'operations' => [
        'healthcheck_token' => env('HEALTHCHECK_TOKEN'),
        'health_alert_cooldown_minutes' => (int) env('HEALTH_ALERT_COOLDOWN_MINUTES', 360),
    ],
    'billing' => [
        'self_service_enabled' => (bool) env('BILLING_SELF_SERVICE_ENABLED', false),
        'integration_key' => env('BILLING_INTEGRATION_KEY'),
        'enforcement_enabled' => (bool) env('LICENSE_ENFORCEMENT_ENABLED', false),
        'stripe_price_ids' => [
            'starter' => env('STRIPE_PRICE_STARTER'),
            'professional' => env('STRIPE_PRICE_PROFESSIONAL'),
            'business' => env('STRIPE_PRICE_BUSINESS'),
        ],
    ],
];
