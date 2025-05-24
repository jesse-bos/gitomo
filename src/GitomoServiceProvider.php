<?php

namespace Gitomo;

use Gitomo\Commands\GitomoCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GitomoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('gitomo')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_gitomo_table')
            ->hasCommand(GitomoCommand::class);
    }
}
