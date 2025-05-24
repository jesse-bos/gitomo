<?php

namespace Gitomo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use OpenAI\Laravel\Facades\OpenAI;

class GitomoCommand extends Command
{
    public $signature = 'gitomo';

    public $description = 'Generate an AI-generated commit message based on staged changes';

    public function handle(): int
    {
        // Always run a quick config check first
        if (! $this->configCheck()) {
            return self::FAILURE;
        }

        $diff = $this->getDiff();
        $type = Arr::get($diff, 'type');

        if (! $content = Arr::get($diff, 'content')) {
            $this->error('No changes found. Make some changes to your files first.');

            return self::FAILURE;
        }

        $this->info("Analyzing {$type} changes...");

        // Generate and display commit message
        try {
            $commitMessage = $this->generateCommitMessage($content, Arr::get($diff, 'files'));

            $this->info('Generated commit message:');
            $this->line('');
            $this->line($commitMessage);

            if ($type === 'unstaged') {
                $this->line('');
                $this->comment('Note: These are unstaged changes. Stage them with git add before committing.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate commit message: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /* -------------------- Helper methods -------------------- */

    private function configCheck(): bool
    {
        $hasErrors = false;

        // Check if OpenAI config exists
        if (! config('openai')) {
            $this->error('✗ OpenAI configuration not found. Run: php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"');
            $hasErrors = true;
        }

        if (! config('openai.api_key')) {
            $this->error('✗ OpenAI API key not found. Add OPENAI_API_KEY=sk-your-key to your .env file');
            $hasErrors = true;
        }

        // Check git repository
        if (! $this->isGitRepository()) {
            $this->error('✗ Not in a git repository');
            $hasErrors = true;
        }

        return ! $hasErrors;
    }

    private function isGitRepository(): bool
    {
        $result = Process::run('git rev-parse --is-inside-work-tree');

        return $result->successful() && trim($result->output()) === 'true';
    }

    /**
     * @return array<string, string>
     */
    private function getDiff(): array
    {
        // First try staged changes
        $stagedProcess = Process::run('git diff --staged');

        if ($stagedProcess->successful() && ! empty(trim($stagedProcess->output()))) {
            $filesProcess = Process::run('git diff --staged --name-status');

            return [
                'type' => 'staged',
                'content' => $stagedProcess->output(),
                'files' => $filesProcess->successful() ? $filesProcess->output() : '',
            ];
        }

        // If no staged changes, try unstaged changes
        $unstagedProcess = Process::run('git diff');

        if ($unstagedProcess->successful() && ! empty(trim($unstagedProcess->output()))) {
            $filesProcess = Process::run('git diff --name-status');

            return [
                'type' => 'unstaged',
                'content' => $unstagedProcess->output(),
                'files' => $filesProcess->successful() ? $filesProcess->output() : '',
            ];
        }

        return [
            'type' => 'none',
            'content' => '',
            'files' => '',
        ];
    }

    private function generateCommitMessage(string $diff, string $filesSummary): string
    {
        $conventional = config('gitomo.commit.conventional', true);
        $maxLength = config('gitomo.commit.max_length', 72);

        // Build the prompt for OpenAI
        $prompt = 'Generate a concise git commit message ';

        if ($conventional) {
            $prompt .= "following the Conventional Commits format (e.g., 'feat: add new feature' or 'fix: resolve issue') ";
        }

        $prompt .= "based on these changes. Keep it under {$maxLength} characters. Return only the commit message, no markdown formatting.";
        $prompt .= "\n\nChanged files:\n$filesSummary\n\nDiff:\n$diff";

        // Request the completion from OpenAI
        $result = OpenAI::chat()->create([
            'model' => config('gitomo.openai.model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that generates concise, meaningful git commit messages based on code changes. Return only the commit message without any markdown formatting or code blocks.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 100,
        ]);

        $commitMessage = trim($result->choices[0]->message->content ?? '');

        return $commitMessage;
    }
}
