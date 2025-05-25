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
    // Arrange
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: "diff --git a/test.php b/test.php\n+new feature"),
        'git diff --staged --name-status' => Process::result(output: "M\ttest.php"),
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'feat: add new feature']],
            ],
        ]),
    ]);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('🔍 Analyzing staged changes...')
        ->expectsOutputToContain('✨ Generated commit message:')
        ->expectsOutputToContain('feat: add new feature')
        ->assertExitCode(0);
});

it('succeeds with valid configuration and unstaged changes', function () {
    // Arrange
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: ''),
        'git diff' => Process::result(output: "diff --git a/test.php b/test.php\n+bug fix"),
        'git diff --name-status' => Process::result(output: "M\ttest.php"),
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'fix: resolve bug']],
            ],
        ]),
    ]);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('🔍 Analyzing unstaged changes...')
        ->expectsOutputToContain('✨ Generated commit message:')
        ->expectsOutputToContain('fix: resolve bug')
        ->expectsOutputToContain('💡 Note: These are unstaged changes')
        ->assertExitCode(0);
});

it('fails when no OpenAI API key is configured', function () {
    // Arrange
    Config::set('openai.api_key', null);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✗ OpenAI API key not found')
        ->expectsOutputToContain('Add OPENAI_API_KEY=')
        ->assertExitCode(1);
});

it('fails when OpenAI configuration is missing', function () {
    // Arrange
    Config::set('openai', null);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✗ OpenAI configuration not found')
        ->expectsOutputToContain('php artisan vendor:publish')
        ->assertExitCode(1);
});

it('fails when not in a git repository', function () {
    // Arrange
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(exitCode: 1),
    ]);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✗ Not in a git repository')
        ->assertExitCode(1);
});

it('fails when no changes are found', function () {
    // Arrange
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: ''),
        'git diff' => Process::result(output: ''),
    ]);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('⚠ No changes found')
        ->assertExitCode(1);
});

it('prioritizes staged over unstaged changes', function () {
    // Arrange
    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: "diff --git a/staged.php b/staged.php\n+staged"),
        'git diff --staged --name-status' => Process::result(output: "M\tstaged.php"),
        'git diff' => Process::result(output: "diff --git a/unstaged.php b/unstaged.php\n+unstaged"),
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'feat: staged changes']],
            ],
        ]),
    ]);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('staged changes')
        ->doesntExpectOutputToContain('unstaged changes')
        ->doesntExpectOutputToContain('Note: These are unstaged changes')
        ->assertExitCode(0);
});

it('uses custom model from configuration', function () {
    // Arrange
    Config::set('openai-commit-messages.openai.model', 'gpt-4');

    Process::fake([
        'git rev-parse --is-inside-work-tree' => Process::result(output: 'true'),
        'git diff --staged' => Process::result(output: "diff --git a/test.php b/test.php\n+change"),
        'git diff --staged --name-status' => Process::result(output: "M\ttest.php"),
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => 'feat: custom model response']],
            ],
        ]),
    ]);

    // Act & Assert
    $this->artisan(OpenAiCommitMessagesCommand::class)
        ->expectsOutputToContain('✨ Generated commit message:')
        ->expectsOutputToContain('feat: custom model response')
        ->assertExitCode(0);
});
