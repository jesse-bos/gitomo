<?php

namespace OpenAICommitMessages;

use OpenAICommitMessages\Commands\OpenAICommitMessagesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OpenAICommitMessagesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('openai-commit-messages')
            ->hasConfigFile()
            ->hasCommand(OpenAICommitMessagesCommand::class);
    }
}
