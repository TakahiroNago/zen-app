<?php

$privatePromptsPath = storage_path('app/private/zen-prompts.php');
$prompts = is_file($privatePromptsPath) ? require $privatePromptsPath : [];

if (! is_array($prompts)) {
    throw new UnexpectedValueException('Private Zen prompts must return an array.');
}

return [

    'prompts' => $prompts,

    /*
    | Emergency stop for new consultations. Set ZEN_CONSULTATIONS_ENABLED=false
    | (then re-cache the config) to stop all AI API calls immediately.
    */
    'consultations_enabled' => (bool) env('ZEN_CONSULTATIONS_ENABLED', true),

    /*
    | Application-wide caps on consultations that reach the AI API, counted
    | per clock hour and per calendar day across all users and guests. They
    | bound the total AI API spend. Set a value to 0 to disable that cap.
    */
    'global_consultation_limits' => [
        'hourly' => (int) env('ZEN_GLOBAL_HOURLY_CONSULTATION_LIMIT', 500),
        'daily' => (int) env('ZEN_GLOBAL_DAILY_CONSULTATION_LIMIT', 3000),
    ],

    /*
    | Optional cap on output tokens per AI API call (max_completion_tokens).
    | Reasoning tokens count towards it, so a value that is too low makes
    | readings fail. Leave unset until the model's limits are confirmed.
    */
    'openai_max_completion_tokens' => env('ZEN_OPENAI_MAX_COMPLETION_TOKENS') === null
        ? null
        : (int) env('ZEN_OPENAI_MAX_COMPLETION_TOKENS'),

    /*
    | Number of days saved consultations that contain detected personal
    | information are kept before they are deleted automatically.
    | Consultations without personal information are kept until the user
    | deletes them. Set to 0 to disable automatic deletion.
    */
    'consultation_retention_days' => (int) env('ZEN_CONSULTATION_RETENTION_DAYS', 365),

    /*
    | Example concerns offered as hint chips on the consultation form. Each
    | entry is a translation key (Japanese source text; see lang/en.json).
    */
    'concern_hints' => [
        '仕事に疲れてしまった',
        '人間関係に悩んでいる',
        '毎日を楽しみたい',
    ],

];
