<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'transcription_model' => env('GEMINI_TRANSCRIPTION_MODEL', 'gemini-3.1-flash-lite'),
        'transcription_fallback_model' => env('GEMINI_TRANSCRIPTION_FALLBACK_MODEL', 'gemini-3.5-flash-lite'),
        'extraction_model' => env('GEMINI_EXTRACTION_MODEL', env('GEMINI_MODEL', 'gemini-3.5-flash-lite')),
        'extraction_fallback_model' => env('GEMINI_EXTRACTION_FALLBACK_MODEL', env('GEMINI_FALLBACK_MODEL', 'gemini-3.6-flash')),
        'request_timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 60),
        'transcription_max_output_tokens' => (int) env('GEMINI_TRANSCRIPTION_MAX_OUTPUT_TOKENS', 4096),
        'extraction_max_output_tokens' => (int) env('GEMINI_EXTRACTION_MAX_OUTPUT_TOKENS', 8192),
        'extraction_temperature' => (float) env('GEMINI_EXTRACTION_TEMPERATURE', 0.2),
        'inline_max_bytes' => (int) env('GEMINI_INLINE_MAX_BYTES', 14680064),
        'project' => env('GOOGLE_CLOUD_PROJECT'),
        'project_number' => env('GOOGLE_CLOUD_PROJECT_NUMBER'),
    ],
    'clinical_ai' => [
        'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffmpeg_timeout' => (int) env('FFMPEG_TIMEOUT', 120),
        'chunk_seconds' => (int) env('CLINICAL_AI_CHUNK_SECONDS', 300),
        'worker_processes' => (int) env('CLINICAL_AI_WORKER_PROCESSES', 7),
    ],

];
