<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAiCommitMessages\Commands\OpenAiCommitMessagesCommand;

beforeEach(function () {
    Config::set('openai.api_key', 'sk-test-key');
    Config::set('openai-commit-messages.openai.model', 'gpt-4o-mini');
    Config::set('openai-commit-messages.commit.conventional', true);
    Config::set('openai-commit-messages.commit.max_length', 72);
});

it('succeeds with valid configuration and staged changes', function () {
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: "diff --git a/test.php b/test.php\n+new feature"),
        'git diff --staged --name-status' => Process::result(output: "M\ttest.php"),
        // Mock clipboard commands for different OS
        'pbcopy' => Process::result(), // macOS
        'xclip -selection clipboard' => Process::result(), // Linux
        'xsel --clipboard --input' => Process::result(), // Linux fallback
        'clip' => Process::result(), // Windows
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'feat: add new feature']],
            ],
        ]),
    ]);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('🔍 Analyzing staged changes...')
        ->expectsOutputToContain('✨ Generated commit message:')
        ->expectsOutputToContain('feat: add new feature')
        ->expectsOutputToContain('📋 Copied to clipboard!')
        ->assertExitCode(0);
});

it('succeeds with valid configuration and unstaged changes', function () {
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: ''),
        'git diff' => Process::result(output: "diff --git a/test.php b/test.php\n+bug fix"),
        'git diff --name-status' => Process::result(output: "M\ttest.php"),
        'pbcopy' => Process::result(),
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'fix: resolve bug']],
            ],
        ]),
    ]);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('🔍 Analyzing unstaged changes...')
        ->expectsOutputToContain('✨ Generated commit message:')
        ->expectsOutputToContain('fix: resolve bug')
        ->expectsOutputToContain('💡 Note: These are unstaged changes')
        ->assertExitCode(0);
});

it('fails when no OpenAI API key is configured', function () {
    Config::set('openai.api_key', null);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✗ OpenAI API key not found')
        ->expectsOutputToContain('Add OPENAI_API_KEY=')
        ->assertExitCode(1);
});

it('fails when OpenAI configuration is missing', function () {
    Config::set('openai', null);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✗ OpenAI configuration not found')
        ->expectsOutputToContain('php artisan vendor:publish')
        ->assertExitCode(1);
});

it('fails when not in a git repository', function () {
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(exitCode: 1),
    ]);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✗ Not in a git repository')
        ->assertExitCode(1);
});

it('fails when no changes are found', function () {
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: ''),
        'git diff' => Process::result(output: ''),
    ]);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('⚠ No changes found')
        ->assertExitCode(1);
});

it('succeeds even when clipboard copy fails', function () {
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: "diff --git a/test.php b/test.php\n+feature"),
        'git diff --staged --name-status' => Process::result(output: "M\ttest.php"),
        'pbcopy' => Process::result(exitCode: 1), // Clipboard fails but command should still succeed
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'feat: add feature']],
            ],
        ]),
    ]);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✨ Generated commit message:')
        ->expectsOutputToContain('feat: add feature')
        ->doesntExpectOutputToContain('📋 Copied to clipboard!')
        ->assertExitCode(0);
});

it('prioritizes staged over unstaged changes', function () {
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: "diff --git a/staged.php b/staged.php\n+staged"),
        'git diff --staged --name-status' => Process::result(output: "M\tstaged.php"),
        'git diff' => Process::result(output: "diff --git a/unstaged.php b/unstaged.php\n+unstaged"),
        'pbcopy' => Process::result(),
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'feat: staged changes']],
            ],
        ]),
    ]);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('staged changes')
        ->doesntExpectOutputToContain('unstaged changes')
        ->doesntExpectOutputToContain('Note: These are unstaged changes')
        ->assertExitCode(0);
});

it('uses custom model from configuration', function () {
    Config::set('openai-commit-messages.openai.model', 'gpt-4');

    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: "diff --git a/test.php b/test.php\n+change"),
        'git diff --staged --name-status' => Process::result(output: "M\ttest.php"),
        'pbcopy' => Process::result(),
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'feat: custom model response']],
            ],
        ]),
    ]);

    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✨ Generated commit message:')
        ->expectsOutputToContain('feat: custom model response')
        ->assertExitCode(0);
});
