<?php

namespace OpenAICommitMessages\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use OpenAICommitMessages\OpenAICommitMessagesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'OpenAICommitMessages\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            OpenAICommitMessagesServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_openai-commit-messages_table.php.stub';
        $migration->up();
        */
    }
}
