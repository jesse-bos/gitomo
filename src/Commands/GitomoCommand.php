<?php

namespace Gitomo\Commands;

use Illuminate\Console\Command;

class GitomoCommand extends Command
{
    public $signature = 'gitomo';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
