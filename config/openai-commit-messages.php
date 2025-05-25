<?php

// OpenAI Commit Messages uses the OpenAI configuration from config/openai.php for the model
// Additional package-specific settings can be configured below

return [
    'openai' => [
        'model' => env('OPENAI_COMMIT_MESSAGES_MODEL', 'gpt-4o-mini'),
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
