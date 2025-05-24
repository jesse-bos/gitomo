<?php

namespace Gitomo;

use Gitomo\Commands\GitomoCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GitomoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('gitomo')
            ->hasConfigFile()
            ->hasCommand(GitomoCommand::class);
    }
}
