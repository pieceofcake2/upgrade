<?php

namespace App\Console\Command;

use App\Console\Command\Task\DownloadTask;
use Cake\Console\Command\Task\ExtractTask;

class ImportShell extends AppShell
{
    public $tasks = [ExtractTask::class, DownloadTask::class];

    public function main()
    {
        $this->out('Import');
    }
}
