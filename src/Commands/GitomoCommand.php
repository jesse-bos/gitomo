<?php

declare(strict_types=1);

namespace Gitomo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use OpenAI\Laravel\Facades\OpenAI;

use function Termwind\render;

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
            render('<div class="text-yellow">⚠ No changes found. Make some changes to your files first.</div>');

            return self::FAILURE;
        }

        render('<div class="text-blue">🔍 Analyzing <span class="font-bold">'.$type.'</span> changes...</div>');

        // Generate and display commit message
        try {
            $commitMessage = $this->generateCommitMessage($content, Arr::get($diff, 'files'));

            render('<div class="text-green font-bold mt-1">✨ Generated commit message:</div>');
            render('<div class="bg-gray text-white p-1 mt-1">'.$commitMessage.'</div>');

            // Copy to clipboard
            if ($this->copyToClipboard($commitMessage)) {
                render('<div class="text-green mt-1">📋 Copied to clipboard!</div>');
            }

            if ($type === 'unstaged') {
                render('<div class="text-yellow mt-1">💡 Note: These are unstaged changes. Stage them with git add before committing.</div>');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            render('<div class="text-red font-bold">❌ Failed to generate commit message:</div>');
            render('<div class="text-red ml-2">'.$e->getMessage().'</div>');

            return self::FAILURE;
        }
    }

    /* -------------------- Helper methods -------------------- */

    protected function configCheck(): bool
    {
        $hasErrors = false;

        // Check if OpenAI config exists
        if (! config('openai')) {
            render('<div class="text-red font-bold">✗ OpenAI configuration not found</div>');
            render('<div class="text-gray ml-2">Run: php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"</div>');
            $hasErrors = true;
        }

        if (! config('openai.api_key')) {
            render('<div class="text-red font-bold">✗ OpenAI API key not found</div>');
            render('<div class="text-gray ml-2">Add OPENAI_API_KEY=sk-your-key to your .env file</div>');
            $hasErrors = true;
        }

        // Check git repository
        if (! $this->isGitRepository()) {
            render('<div class="text-red font-bold">✗ Not in a git repository</div>');
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

    private function copyToClipboard(string $text): bool
    {
        // Detect operating system and use appropriate clipboard command
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
