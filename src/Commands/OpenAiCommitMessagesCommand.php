<?php

declare(strict_types=1);

namespace OpenAiCommitMessages\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiCommitMessagesCommand extends Command
{
    public $signature = 'openai:commit-message';

    public $description = 'Generate an AI-generated commit message based on staged or unstaged changes using OpenAI';

    public function handle(): int
    {
        if (! $this->configCheck()) {
            return self::FAILURE;
        }

        $diff = $this->getDiff();

        if (! $content = Arr::get($diff, 'content')) {
            $this->warn('⚠ No changes found. Make some changes to your files first.');

            return self::FAILURE;
        }

        $type = Arr::get($diff, 'type');

        $this->info("🔍 Analyzing {$type} changes...");

        $prompt = $this->buildPrompt($content, Arr::get($diff, 'files'));

        try {
            $commitMessage = $this->generateCommitMessage($prompt);
        } catch (\Exception $e) {
            $this->error('❌ Failed to generate commit message:');
            $this->line('  '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✨ Generated commit message:');
        $this->line('  '.$commitMessage);

        if ($this->copyToClipboard($commitMessage)) {
            $this->info('📋 Copied to clipboard!');
        }

        if ($type === 'unstaged') {
            $this->comment('💡 Note: These are unstaged changes. Stage them with git add or in the GUI before committing.');
        }

        return self::SUCCESS;
    }

    /* -------------------- Helper methods -------------------- */

    protected function configCheck(): bool
    {
        $hasErrors = false;

        if (! config('openai')) {
            $this->error('✗ OpenAI configuration not found');
            $this->line('  Run: php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"');
            $hasErrors = true;
        }

        if (! config('openai.api_key')) {
            $this->error('✗ OpenAI API key not found');
            $this->line('  Add OPENAI_API_KEY=sk-your-key to your .env file');
            $hasErrors = true;
        }

        if (! $this->isGitRepository()) {
            $this->error('✗ Not in a git repository');
            $hasErrors = true;
        }

        return ! $hasErrors;
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
        $stagedProcess = Process::run('git diff --staged');

        if ($stagedProcess->successful() && ! empty(trim($stagedProcess->output()))) {
            $filesProcess = Process::run('git diff --staged --name-status');

            return [
                'type' => 'staged',
                'content' => $stagedProcess->output(),
                'files' => $filesProcess->successful() ? $filesProcess->output() : '',
            ];
        }

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

    protected function generateCommitMessage(string $prompt): string
    {
        $result = OpenAI::chat()->create([
            'model' => config('openai-commit-messages.openai.model', 'gpt-4o-mini'),
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

    protected function buildPrompt(string $diff, string $filesSummary): string
    {
        $conventional = config('openai-commit-messages.commit.conventional', true);
        $maxLength = config('openai-commit-messages.commit.max_length', 72);

        $prompt = 'Generate a concise git commit message ';

        if ($conventional) {
            $prompt .= "following the Conventional Commits format (e.g., 'feat: add new feature' or 'fix: resolve issue') ";
        }

        $prompt .= "based on these changes. Keep it under {$maxLength} characters. Return only the commit message, no markdown formatting.";
        $prompt .= "\n\nChanged files:\n$filesSummary\n\nDiff:\n$diff";

        return $prompt;
    }

    protected function copyToClipboard(string $text): bool
    {
        $os = PHP_OS_FAMILY;

        try {
            switch ($os) {
                case 'Darwin': // macOS
                    $process = Process::input($text)->run('pbcopy');
                    break;

                case 'Linux':
                    // Try xclip first, then xsel as fallback
                    $process = Process::input($text)->run(['xclip', '-selection', 'clipboard']);

                    if (! $process->successful()) {
                        $process = Process::input($text)->run(['xsel', '--clipboard', '--input']);
                    }
                    break;

                case 'Windows':
                    $process = Process::input($text)->run('clip');
                    break;

                default:
                    return false;
            }

            return $process->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
