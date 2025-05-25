<?php

namespace OpenAiCommitMessages\Tests;

use OpenAiCommitMessages\OpenAiCommitMessagesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            OpenAiCommitMessagesServiceProvider::class,
        ];
    }
}
