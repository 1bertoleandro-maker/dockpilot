<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI driver
    |--------------------------------------------------------------------------
    |
    | fake  — returns fixture envelopes (dev / KingHost smoke, no tokens)
    | openai — GPT Structured Outputs via HTTP
    |
    */

    'driver' => env('AI_DRIVER', 'fake'),

    'prompt_version' => 'extract.v1',

    'contract_version' => 'ai.extract.v1',

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    ],

    'fixtures_path' => resource_path('ai/fixtures'),

    'disk' => env('AI_DOCUMENTS_DISK', env('FILESYSTEM_DISK', 'local')),

];
