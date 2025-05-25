<?php

namespace OpenAiCommitMessages;

use OpenAiCommitMessages\Commands\OpenAiCommitMessagesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OpenAiCommitMessagesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('openai-commit-messages')
            ->hasConfigFile()
            ->hasCommand(OpenAiCommitMessagesCommand::class);
    }
}
