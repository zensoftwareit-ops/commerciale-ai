<?php

return [
    'ai_provider' => env('AI_PROVIDER', 'fake'),
    'webhook_replay_window_seconds' => (int) env('WEBHOOK_REPLAY_WINDOW_SECONDS', 300),
];
