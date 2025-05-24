<?php

// Gitomo uses the OpenAI configuration from config/openai.php for the model
// Additional Gitomo-specific settings can be configured below

return [
    'openai' => [
        'model' => env('GITOMO_OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Commit Message Settings
    |--------------------------------------------------------------------------
    |
    | Configure how the commit messages should be generated
    |
    */
    'commit' => [
        // Maximum length for the commit message
        'max_length' => 72,

        // Use conventional commit format (e.g. feat: message)
        'conventional' => true,
    ],
];
