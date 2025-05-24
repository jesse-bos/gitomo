<?php

namespace Gitomo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use OpenAI\Laravel\Facades\OpenAI;

class GitomoCommand extends Command
{
    public $signature = 'gitomo {--check : Check if Gitomo is properly configured}';

    public $description = 'Generate an AI-generated commit message based on staged changes';

    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->checkConfiguration();
        }

        // Check if we're in a git repository
        if (! $this->isGitRepository()) {
            $this->error('Not a git repository!');

            return self::FAILURE;
        }

        // Try to get staged changes first, then unstaged changes
        $diff = $this->getDiff();

        if (empty($diff['content'])) {
            $this->error('No changes found. Make some changes to your files first.');

            return self::FAILURE;
        }

        $this->info("Analyzing {$diff['type']} changes...");

        // Generate and display commit message
        try {
            $commitMessage = $this->generateCommitMessage($diff['content'], $diff['files']);

            $this->info('Generated commit message:');
            $this->line('');
            $this->line($commitMessage);

            if ($diff['type'] === 'unstaged') {
                $this->line('');
                $this->comment('Note: These are unstaged changes. Stage them with git add before committing.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate commit message: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function checkConfiguration(): int
    {
        $this->info('Checking Gitomo configuration...');
        $this->line('');

        $allGood = true;

        // Check OpenAI API key
        $apiKey = config('openai.api_key');
        if ($apiKey) {
            $this->info('✓ OpenAI API key found');
        } else {
            $this->error('✗ OpenAI API key not found in .env file');
            $this->line('  Add OPENAI_API_KEY=sk-your-key to your .env file');
            $allGood = false;
        }

        // Check if OpenAI config exists
        if (config('openai')) {
            $this->info('✓ OpenAI configuration found');
        } else {
            $this->error('✗ OpenAI configuration not found');
            $this->line('  Run: php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"');
            $allGood = false;
        }

        // Check git repository
        if ($this->isGitRepository()) {
            $this->info('✓ Git repository detected');
        } else {
            $this->error('✗ Not in a git repository');
            $allGood = false;
        }

        // Check model configuration
        $model = config('gitomo.openai.model', 'gpt-4o-mini');
        $this->info("✓ Using model: {$model}");

        $this->line('');

        if ($allGood) {
            $this->info('🎉 Gitomo is properly configured and ready to use!');
            $this->line('Run "php artisan gitomo" to generate a commit message.');

            return self::SUCCESS;
        } else {
            $this->error('❌ Gitomo configuration incomplete. Please fix the issues above.');

            return self::FAILURE;
        }
    }

    protected function isGitRepository(): bool
    {
        $result = Process::run('git rev-parse --is-inside-work-tree');

        return $result->successful() && trim($result->output()) === 'true';
    }

    /**
     * @return array<string, string>
     */
    protected function getDiff(): array
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

    protected function generateCommitMessage(string $diff, string $filesSummary): string
    {
        $conventional = config('gitomo.commit.conventional', true);
        $maxLength = config('gitomo.commit.max_length', 72);

        // Build the prompt for OpenAI
        $prompt = 'Generate a concise git commit message ';

        if ($conventional) {
            $prompt .= "following the Conventional Commits format (e.g., 'feat: add new feature' or 'fix: resolve issue') ";
        }

        $prompt .= "based on these changes. Keep it under {$maxLength} characters.";
        $prompt .= "\n\nChanged files:\n$filesSummary\n\nDiff:\n$diff";

        // Request the completion from OpenAI
        $result = OpenAI::chat()->create([
            'model' => config('gitomo.openai.model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that generates concise, meaningful git commit messages based on code changes.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 100,
        ]);

        $commitMessage = trim($result->choices[0]->message->content ?? '');

        return $commitMessage;
    }
}
